import { pool } from '../db/connection';
import { v7 as uuidv7 } from 'uuid';
import { RowDataPacket } from 'mysql2';

async function migrateTable(
  tableName: string,
  idCol: string = 'id',
  afterCol: string = 'id'
) {
  console.log(`\n========================================`);
  console.log(`[Migrate] Processing table: ${tableName}`);
  console.log(`========================================`);

  // 1. Check if uid column exists
  const [cols] = await pool.query<RowDataPacket[]>(`
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ? 
      AND COLUMN_NAME = 'uid'
  `, [tableName]);

  if (cols.length === 0) {
    console.log(`-> Adding column 'uid' to ${tableName}...`);
    await pool.query(`ALTER TABLE \`${tableName}\` ADD COLUMN \`uid\` CHAR(36) NULL AFTER \`${afterCol}\``);
  } else {
    console.log(`-> Column 'uid' already exists on ${tableName}.`);
  }

  // 2. Fetch all rows where uid IS NULL
  const [rows] = await pool.query<RowDataPacket[]>(`
    SELECT \`${idCol}\` FROM \`${tableName}\` WHERE \`uid\` IS NULL OR \`uid\` = ''
  `);

  console.log(`-> Found ${rows.length} rows needing UUID v7 in ${tableName}.`);

  if (rows.length > 0) {
    const batchSize = 500;
    for (let i = 0; i < rows.length; i += batchSize) {
      const batch = rows.slice(i, i + batchSize);
      for (const row of batch) {
        const uid = uuidv7();
        await pool.query(
          `UPDATE \`${tableName}\` SET \`uid\` = ? WHERE \`${idCol}\` = ?`,
          [uid, row[idCol]]
        );
      }
      process.stdout.write(`   Updated ${Math.min(i + batchSize, rows.length)} / ${rows.length} rows...\r`);
    }
    console.log(`\n-> Successfully backfilled UUID v7 for ${rows.length} rows.`);
  }

  // 3. Ensure NOT NULL and UNIQUE index
  const [indexes] = await pool.query<RowDataPacket[]>(`
    SELECT INDEX_NAME 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ? 
      AND INDEX_NAME = ?
  `, [tableName, `idx_${tableName}_uid`]);

  if (indexes.length === 0) {
    console.log(`-> Adding UNIQUE index 'idx_${tableName}_uid' to ${tableName}...`);
    await pool.query(`ALTER TABLE \`${tableName}\` MODIFY COLUMN \`uid\` CHAR(36) NOT NULL, ADD UNIQUE KEY \`idx_${tableName}_uid\` (\`uid\`)`);
  } else {
    console.log(`-> UNIQUE index already exists on ${tableName}.`);
  }
}

async function run() {
  try {
    console.log('[Start] Dual-Key UUID v7 Migration');

    // Migrate main tables
    await migrateTable('user', 'id', 'id');
    await migrateTable('docz', 'id', 'id');
    await migrateTable('doc_cat_name', 'id', 'id');
    await migrateTable('doc_profile', 'id', 'id');
    await migrateTable('doc_file', 'id', 'id');

    console.log('\n========================================');
    console.log('[Success] All tables migrated to Dual-Key UUID v7 successfully!');
    console.log('========================================\n');
  } catch (err) {
    console.error('[Error] Migration failed:', err);
  } finally {
    await pool.end();
  }
}

run();
