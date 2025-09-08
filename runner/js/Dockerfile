FROM node:20-alpine

RUN adduser -D -H -u 10001 runner
USER runner

WORKDIR /app

COPY evaluator.js /app/evaluator.js

ENTRYPOINT ["node", "/app/evaluator.js"]
