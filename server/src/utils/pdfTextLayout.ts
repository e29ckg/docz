import { PDFFont } from 'pdf-lib';

const words = new Intl.Segmenter('th', { granularity: 'word' });
const graphemes = new Intl.Segmenter('th', { granularity: 'grapheme' });

/** Wrap using the embedded font's actual widths, preserving explicit newlines. */
export function wrapPdfText(text: string, font: PDFFont, size: number, maxWidth: number): string[] {
  const lines: string[] = [];
  for (const paragraph of text.replace(/\r\n?/g, '\n').split('\n')) {
    let line = '';
    const append = (token: string) => {
      const candidate = line + token;
      if (font.widthOfTextAtSize(candidate.trimEnd(), size) <= maxWidth) {
        line = candidate;
      } else {
        if (line.trim()) lines.push(line.trimEnd());
        line = token.trimStart();
      }
    };
    for (const { segment } of words.segment(paragraph)) {
      // Long identifiers/URLs can exceed a whole line. Never split Thai combining marks.
      if (font.widthOfTextAtSize(segment, size) > maxWidth) {
        for (const cluster of graphemes.segment(segment)) append(cluster.segment);
      } else {
        append(segment);
      }
    }
    lines.push(line.trimEnd());
  }
  return lines;
}
