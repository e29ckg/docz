import { v7 as uuidv7 } from 'uuid';

/**
 * Checks whether the given string is a valid UUID format (v1-v7)
 */
export const isUUID = (val: any): boolean => {
  if (!val) return false;
  const str = Array.isArray(val) ? String(val[0]) : String(val);
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(str.trim());
};

/**
 * Generates a modern time-ordered UUID v7 (RFC 9562)
 */
export const generateUid = (): string => {
  return uuidv7();
};
