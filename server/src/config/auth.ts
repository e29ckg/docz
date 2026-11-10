import dotenv from 'dotenv';
import path from 'path';

dotenv.config({ path: path.resolve(__dirname, '../../.env') });

export const JWT_SECRET: string = (() => {
  const secret = process.env.JWT_SECRET;
  if (!secret) throw new Error('Configure JWT_SECRET in server/.env before starting DOCZ. Run npm run setup for initial configuration.');
  return secret;
})();
