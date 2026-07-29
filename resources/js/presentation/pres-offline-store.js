const DATABASE_NAME = 'abah-presentation';
const DATABASE_VERSION = 1;
const STORE_NAME = 'payloads';
const MAX_PERIODS = 3;

const requestAsPromise = (request) => new Promise((resolve, reject) => {
    request.addEventListener('success', () => resolve(request.result), { once: true });
    request.addEventListener('error', () => reject(request.error), { once: true });
});

export class PresentationOfflineStore {
    constructor(indexedDb = window.indexedDB) {
        this.indexedDb = indexedDb;
        this.databasePromise = null;
    }

    available() {
        return Boolean(this.indexedDb);
    }

    async database() {
        if (!this.available()) {
            return null;
        }

        if (!this.databasePromise) {
            this.databasePromise = new Promise((resolve, reject) => {
                const request = this.indexedDb.open(DATABASE_NAME, DATABASE_VERSION);
                request.addEventListener('upgradeneeded', () => {
                    const database = request.result;
                    if (!database.objectStoreNames.contains(STORE_NAME)) {
                        const store = database.createObjectStore(STORE_NAME, { keyPath: 'period' });
                        store.createIndex('savedAt', 'savedAt');
                    }
                }, { once: true });
                request.addEventListener('success', () => resolve(request.result), { once: true });
                request.addEventListener('error', () => reject(request.error), { once: true });
            }).catch((error) => {
                console.warn('Presentation offline database is unavailable.', error);
                this.databasePromise = null;
                return null;
            });
        }

        return this.databasePromise;
    }

    async get(period) {
        const database = await this.database();
        if (!database || !period) {
            return null;
        }

        try {
            const transaction = database.transaction(STORE_NAME, 'readonly');
            const record = await requestAsPromise(transaction.objectStore(STORE_NAME).get(period));
            return record?.payload || null;
        } catch (error) {
            console.warn('Presentation offline payload could not be read.', error);
            return null;
        }
    }

    async latest() {
        const database = await this.database();
        if (!database) {
            return null;
        }

        try {
            const transaction = database.transaction(STORE_NAME, 'readonly');
            const records = await requestAsPromise(transaction.objectStore(STORE_NAME).getAll());
            const latest = records
                .slice()
                .sort((left, right) => Number(right.savedAt || 0) - Number(left.savedAt || 0))[0];
            return latest?.payload || null;
        } catch (error) {
            console.warn('Latest presentation offline payload could not be read.', error);
            return null;
        }
    }

    async put(period, payload) {
        const database = await this.database();
        if (!database || !period || !payload) {
            return;
        }

        try {
            const transaction = database.transaction(STORE_NAME, 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            store.put({
                period,
                savedAt: Date.now(),
                payload,
            });
            await new Promise((resolve, reject) => {
                transaction.addEventListener('complete', resolve, { once: true });
                transaction.addEventListener('abort', () => reject(transaction.error), { once: true });
                transaction.addEventListener('error', () => reject(transaction.error), { once: true });
            });
            await this.trim();
        } catch (error) {
            console.warn('Presentation offline payload could not be saved.', error);
        }
    }

    async trim() {
        const database = await this.database();
        if (!database) {
            return;
        }

        const readTransaction = database.transaction(STORE_NAME, 'readonly');
        const records = await requestAsPromise(readTransaction.objectStore(STORE_NAME).getAll());
        const expired = records
            .slice()
            .sort((left, right) => Number(right.savedAt || 0) - Number(left.savedAt || 0))
            .slice(MAX_PERIODS);

        if (!expired.length) {
            return;
        }

        const deleteTransaction = database.transaction(STORE_NAME, 'readwrite');
        const store = deleteTransaction.objectStore(STORE_NAME);
        expired.forEach((record) => store.delete(record.period));
    }
}

