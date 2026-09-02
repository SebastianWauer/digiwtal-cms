if (typeof Promise.withResolvers !== 'function') {
  Object.defineProperty(Promise, 'withResolvers', {
    configurable: true,
    value() {
      let resolve;
      let reject;
      const promise = new Promise((promiseResolve, promiseReject) => {
        resolve = promiseResolve;
        reject = promiseReject;
      });
      return { promise, resolve, reject };
    },
  });
}

if (typeof Uint8Array.prototype.toHex !== 'function') {
  Object.defineProperty(Uint8Array.prototype, 'toHex', {
    configurable: true,
    value() {
      let result = '';
      for (const byte of this) result += byte.toString(16).padStart(2, '0');
      return result;
    },
  });
}

const workerModule = await import('./pdf.worker.mjs');
const { WorkerMessageHandler } = workerModule;

export { WorkerMessageHandler };
