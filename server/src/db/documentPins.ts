import { pool } from './connection';

export const documentPinsSchema = `CREATE TABLE IF NOT EXISTS doc_user_pin (
  user_id INT NOT NULL,
  doc_id INT NOT NULL,
  pinned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`;

let ready: Promise<unknown> | undefined;

/** Additive schema setup; retry if MySQL was temporarily unavailable. */
export function ensureDocumentPins(): Promise<unknown> {
  if (!ready) {
    ready = pool.query('SELECT 1 FROM doc_user_pin LIMIT 0').catch(error => {
      if (error.code !== 'ER_NO_SUCH_TABLE') throw error;
      return pool.query(documentPinsSchema);
    }).catch(error => {
      ready = undefined;
      throw error;
    });
  }
  return ready;
}
