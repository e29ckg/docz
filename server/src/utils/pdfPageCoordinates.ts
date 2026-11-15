import { PDFPage, concatTransformationMatrix, pushGraphicsState, popGraphicsState } from 'pdf-lib';

/** Coordinates in the visible, upright page, accounting for CropBox and /Rotate. */
export function getVisiblePageCoordinates(page: PDFPage) {
  const media = page.getMediaBox();
  const crop = page.getCropBox();
  const x = Math.max(media.x, crop.x);
  const y = Math.max(media.y, crop.y);
  const width = Math.min(media.x + media.width, crop.x + crop.width) - x;
  const height = Math.min(media.y + media.height, crop.y + crop.height) - y;
  if (width <= 0 || height <= 0) throw new Error('PDF page has an empty visible area');
  const rotation = ((page.getRotation().angle % 360) + 360) % 360;
  const transforms: Record<number, [number, number, number, number, number, number]> = {
    0: [1, 0, 0, 1, x, y],
    90: [0, 1, -1, 0, x + width, y],
    180: [-1, 0, 0, -1, x + width, y + height],
    270: [0, -1, 1, 0, x, y + height],
  };
  const transform = transforms[rotation];
  if (!transform) throw new Error(`Unsupported PDF rotation: ${rotation}`);
  const swapped = rotation === 90 || rotation === 270;
  return { width: swapped ? height : width, height: swapped ? width : height, transform };
}

export function drawOnVisiblePage(page: PDFPage, draw: (size: { width: number; height: number }) => void) {
  const visible = getVisiblePageCoordinates(page);
  page.pushOperators(pushGraphicsState(), concatTransformationMatrix(...visible.transform));
  try {
    draw(visible);
  } finally {
    page.pushOperators(popGraphicsState());
  }
}
