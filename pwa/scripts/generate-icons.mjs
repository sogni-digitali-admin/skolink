// Generates simple branded PNG placeholder icons (regular + maskable) with no dependencies.
// Brand mark: brand-color field with a white ring (a stylised "o" of GegoK12).
import { deflateSync } from 'node:zlib'
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const BRAND = [0x1a, 0x56, 0xdb] // #1a56db
const WHITE = [0xff, 0xff, 0xff]
const outDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'public', 'icons')

// --- minimal PNG encoder (RGBA, no filtering) ---
const crcTable = new Int32Array(256).map((_, n) => {
  let c = n
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
  return c
})
function crc32(buf) {
  let c = -1
  for (let i = 0; i < buf.length; i++) c = crcTable[(c ^ buf[i]) & 0xff] ^ (c >>> 8)
  return (c ^ -1) >>> 0
}
function chunk(type, data) {
  const len = Buffer.alloc(4)
  len.writeUInt32BE(data.length)
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data])
  const crc = Buffer.alloc(4)
  crc.writeUInt32BE(crc32(body))
  return Buffer.concat([len, body, crc])
}
function encodePng(pixels, size) {
  const ihdr = Buffer.alloc(13)
  ihdr.writeUInt32BE(size, 0)
  ihdr.writeUInt32BE(size, 4)
  ihdr[8] = 8 // bit depth
  ihdr[9] = 6 // RGBA
  const raw = Buffer.alloc(size * (size * 4 + 1))
  for (let y = 0; y < size; y++) {
    raw[y * (size * 4 + 1)] = 0 // filter: none
    pixels.copy(raw, y * (size * 4 + 1) + 1, y * size * 4, (y + 1) * size * 4)
  }
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ])
}

function render(size, maskable) {
  const px = Buffer.alloc(size * size * 4)
  const cx = size / 2
  const ringOuter = size * (maskable ? 0.32 : 0.3)
  const ringInner = ringOuter * 0.55
  const corner = maskable ? 0 : size * 0.18 // rounded corners for regular icon
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const i = (y * size + x) * 4
      let a = 255
      if (corner > 0) {
        // transparent outside rounded rect
        const dx = Math.max(corner - x, 0, x - (size - 1 - corner))
        const dy = Math.max(corner - y, 0, y - (size - 1 - corner))
        if (dx * dx + dy * dy > corner * corner) a = 0
      }
      const d = Math.hypot(x + 0.5 - cx, y + 0.5 - cx)
      const inRing = d <= ringOuter && d >= ringInner
      const [r, g, b] = inRing ? WHITE : BRAND
      px[i] = r
      px[i + 1] = g
      px[i + 2] = b
      px[i + 3] = a
    }
  }
  return encodePng(px, size)
}

mkdirSync(outDir, { recursive: true })
for (const size of [192, 512]) {
  writeFileSync(join(outDir, `icon-${size}.png`), render(size, false))
  writeFileSync(join(outDir, `icon-maskable-${size}.png`), render(size, true))
}
console.log('icons written to', outDir)
