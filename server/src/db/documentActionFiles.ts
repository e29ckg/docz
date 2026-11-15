import { pool } from './connection';

export const documentActionFilesSchema = `CREATE TABLE IF NOT EXISTS doc_user_action_file (
  uid CHAR(36) NOT NULL PRIMARY KEY,
  doc_id INT NOT NULL,
  user_id INT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  size_bytes INT NOT NULL,
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL DEFAULT NULL,
  KEY idx_action_file_owner (doc_id, user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`;

let ready: Promise<unknown> | undefined;
export function ensureDocumentActionFiles(): Promise<unknown> {
  if (!ready) {
    ready = pool.query('SELECT deleted_at FROM doc_user_action_file LIMIT 0').catch(error => {
      if (error.code === 'ER_NO_SUCH_TABLE') return pool.query(documentActionFilesSchema);
      if (error.code === 'ER_BAD_FIELD_ERROR') {
        return pool.query('ALTER TABLE doc_user_action_file ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL').catch(alterError => {
          if (alterError.code !== 'ER_DUP_FIELDNAME') throw alterError;
        });
      }
      throw error;
    }).catch(error => { ready = undefined; throw error; });
  }
  return ready;
}
