const vm = require('node:vm');

function sanitizeCode(code) {
  return String(code)
    .replace(/\bexport\s+default\s+function\s+([A-Za-z0-9_]+)?/g, 'function $1')
    .replace(/\bexport\s+function\s+/g, 'function ')
    .replace(/\bexport\s+(const|let|var)\s+/g, '$1 ');
}

function deepEqual(a, b) {
  try { return JSON.stringify(a) === JSON.stringify(b); }
  catch { return false; }
}

(async () => {
  try {
    const chunks = [];
    for await (const ch of process.stdin) chunks.push(ch);
    const input = JSON.parse(Buffer.concat(chunks).toString('utf8'));

    const code = sanitizeCode(input.code ?? '');
    const tests = input.tests || {};
    const fnName = String(tests.function || '');
    const cases = Array.isArray(tests.cases) ? tests.cases : [];
    const eqMode = (tests.equality || 'deep').toLowerCase();
    const timeout = Number(tests.timeoutMs || 1000);

    if (!fnName) throw new Error('Function name is required');

    const sandbox = {
      module: { exports: {} },
      exports: {},
      console: { log: () => {} },
      global: null,
      setTimeout, clearTimeout,
    };
    sandbox.global = sandbox;

    const context = vm.createContext(sandbox, { name: 'user-sandbox' });

    const userScript = new vm.Script(code, { filename: 'user-code.js' });
    userScript.runInContext(context, { timeout });

    let fn = context[fnName];
    if (typeof fn !== 'function' && context.module && context.module.exports) {
      fn = context.module.exports[fnName];
    }
    if (typeof fn !== 'function') throw new Error(`Function '${fnName}' not found`);

    const results = [];
    let passedCount = 0;

    for (let i = 0; i < cases.length; i++) {
      const c = cases[i] || {};
      const args = Array.isArray(c.args) ? c.args : [];
      const expect = c.expect;
      const expectThrows = !!c.throws;

      context.__args = args;
      context.__result = undefined;

      try {
        const call = new vm.Script(`__result = (${fnName})(...__args);`, { filename: 'call.js' });
        call.runInContext(context, { timeout });

        if (expectThrows) {
          results.push({ index: i, pass: false, error: 'Expected to throw, but returned' });
        } else {
          const res = context.__result;
          const ok = eqMode === 'strict' ? res === expect : deepEqual(res, expect);
          ok ? (passedCount++, results.push({ index: i, pass: true })) :
               results.push({ index: i, pass: false, error: `Expected ${JSON.stringify(expect)}, got ${JSON.stringify(res)}` });
        }
      } catch (err) {
        if (expectThrows) {
          passedCount++; results.push({ index: i, pass: true });
        } else {
          results.push({ index: i, pass: false, error: String(err && err.message || err) });
        }
      } finally {
        delete context.__args; delete context.__result;
      }
    }

    const out = {
      passed: passedCount === cases.length && cases.length > 0,
      passedCount,
      total: cases.length,
      results,
      output: `Tests: ${passedCount}/${cases.length} passed`,
      feedback: (results.find(r => !r.pass)?.error) ? `Case #${(results.findIndex(r => !r.pass))+1}: ${results.find(r => !r.pass).error}` : ''
    };

    process.stdout.write(JSON.stringify(out));
  } catch (e) {
    process.stdout.write(JSON.stringify({
      passed: false, passedCount: 0, total: 0,
      results: [], output: 'Runner error', feedback: String(e && e.message || e)
    }));
    process.exit(0);
  }
})();
