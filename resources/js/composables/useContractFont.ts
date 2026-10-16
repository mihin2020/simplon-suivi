/**
 * Fonts a contract can be printed in (App\Enums\ContractFont::options()).
 * The same .ttf files feed the PDF and the on-screen editor, so what is seen
 * while editing is what gets printed.
 */
export interface ContractFontOption {
    value: string
    label: string
    family: string
    /** URL prefix of the four .ttf files; null for fonts built into the PDF engine. */
    files: string | null
    serif: boolean
}

const FACES: Array<[suffix: string, weight: string, style: string]> = [
    ['regular', '400', 'normal'],
    ['bold', '700', 'normal'],
    ['italic', '400', 'italic'],
    ['bolditalic', '700', 'italic'],
]

/** Declare the font's @font-face once per page; the browser loads it on first use. */
export function loadContractFont(font: ContractFontOption | null | undefined): void {
    if (!font?.files || typeof document === 'undefined') return

    const id = `contract-font-${font.value}`
    if (document.getElementById(id)) return

    const style = document.createElement('style')
    style.id = id
    style.textContent = FACES.map(([suffix, weight, style]) =>
        `@font-face{font-family:'${font.family}';font-weight:${weight};font-style:${style};font-display:swap;src:url('${font.files}-${suffix}.ttf') format('truetype');}`,
    ).join('\n')
    document.head.appendChild(style)
}

/** CSS font-family stack, with a fallback of the same kind. */
export function contractFontStack(font: ContractFontOption | null | undefined): string {
    if (!font) return "'DejaVu Serif', Georgia, 'Times New Roman', serif"

    return font.serif
        ? `'${font.family}', 'DejaVu Serif', Georgia, serif`
        : `'${font.family}', 'DejaVu Sans', Arial, sans-serif`
}
