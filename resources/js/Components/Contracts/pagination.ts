import { Extension } from '@tiptap/core'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { Decoration, DecorationSet, type EditorView } from '@tiptap/pm/view'

export const PX_PER_MM = 96 / 25.4
const PAGE_HEIGHT_MM = 297
/** Margin of a page that carries no letterhead band, and gap around a band. Mirrors pdfs/contract.blade.php. */
export const PLAIN_MARGIN_MM = 20
export const BAND_GAP_MM = 6

export interface PageSetup {
    headerMm: number
    footerMm: number
    headerUrl: string | null
    footerUrl: string | null
    firstPageOnly: boolean
}

interface PageBreak { pos: number; filler: number; page: number }

export interface PaginationState { pages: number; lastFiller: number }

const hasBand = (setup: PageSetup, side: 'header' | 'footer', page: number) =>
    (side === 'header' ? setup.headerMm : setup.footerMm) > 0 && (!setup.firstPageOnly || page === 1)

/** Height reserved at the top / bottom of a page, in mm. */
export const areaMm = (setup: PageSetup, side: 'header' | 'footer', page: number) =>
    hasBand(setup, side, page) ? (side === 'header' ? setup.headerMm : setup.footerMm) + BAND_GAP_MM : PLAIN_MARGIN_MM

const contentHeightPx = (setup: PageSetup, page: number) =>
    (PAGE_HEIGHT_MM - areaMm(setup, 'header', page) - areaMm(setup, 'footer', page)) * PX_PER_MM

/** A page edge: the reserved area, with the letterhead image when the page carries one. */
export function buildArea(setup: PageSetup, side: 'header' | 'footer', page: number): HTMLElement {
    const area = document.createElement('div')
    area.className = `page-area page-area-${side}`
    area.style.height = `${areaMm(setup, side, page)}mm`

    const url = side === 'header' ? setup.headerUrl : setup.footerUrl
    if (hasBand(setup, side, page) && url) {
        const image = document.createElement('img')
        image.src = url
        image.alt = ''
        image.draggable = false
        image.style.height = `${side === 'header' ? setup.headerMm : setup.footerMm}mm`
        area.appendChild(image)
    }

    return area
}

function buildBreak(setup: PageSetup, item: PageBreak): HTMLElement {
    const wrapper = document.createElement('div')
    wrapper.className = 'page-break'
    wrapper.contentEditable = 'false'

    const filler = document.createElement('div')
    filler.style.height = `${item.filler}px`

    const gap = document.createElement('div')
    gap.className = 'page-gap'
    gap.textContent = `Page ${item.page + 1}`

    wrapper.append(filler, buildArea(setup, 'footer', item.page), gap, buildArea(setup, 'header', item.page + 1))

    return wrapper
}

const key = new PluginKey<{ breaks: PageBreak[]; version: number }>('contractPagination')

/**
 * Lays the document out on A4 pages: when a block would overflow the page,
 * a non-editable break (end of page, gap, top of next page) is drawn before
 * it. Breaks fall between blocks; the PDF may also split a long paragraph,
 * so the page count is a close preview, not a guarantee.
 */
export const Pagination = Extension.create<{
    setup: () => PageSetup
    /** Height of what precedes the text on page 1 (the document title), in px. */
    leadingHeight: () => number
    onLayout: (state: PaginationState) => void
}>({
    name: 'contractPagination',

    addOptions() {
        return {
            setup: () => ({ headerMm: 0, footerMm: 0, headerUrl: null, footerUrl: null, firstPageOnly: false }),
            leadingHeight: () => 0,
            onLayout: () => {},
        }
    },

    addProseMirrorPlugins() {
        const options = this.options

        const measure = (view: EditorView): { breaks: PageBreak[]; layout: PaginationState } => {
            const setup = options.setup()
            const breaks: PageBreak[] = []
            let page = 1
            let used = options.leadingHeight()
            let previousMargin = 0

            view.state.doc.forEach((_node, offset) => {
                const dom = view.nodeDOM(offset)
                if (!(dom instanceof HTMLElement)) return

                const style = getComputedStyle(dom)
                const marginTop = parseFloat(style.marginTop) || 0
                const marginBottom = parseFloat(style.marginBottom) || 0
                const height = dom.getBoundingClientRect().height
                // Adjacent vertical margins collapse to the larger one.
                const needed = Math.max(previousMargin, marginTop) + height

                if (used > 0 && used + needed > contentHeightPx(setup, page)) {
                    breaks.push({ pos: offset, page, filler: Math.max(0, Math.round(contentHeightPx(setup, page) - used - previousMargin)) })
                    page++
                    used = marginTop + height
                } else {
                    used += needed
                }
                previousMargin = marginBottom
            })

            return {
                breaks,
                layout: { pages: page, lastFiller: Math.max(0, Math.round(contentHeightPx(setup, page) - used - previousMargin)) },
            }
        }

        return [
            new Plugin({
                key,
                state: {
                    init: () => ({ breaks: [] as PageBreak[], version: 0 }),
                    apply(tr, value) {
                        const next = tr.getMeta(key) as { breaks: PageBreak[] } | undefined
                        if (next) return { breaks: next.breaks, version: value.version + 1 }
                        if (!tr.docChanged) return value

                        // Keep the breaks attached to their blocks until the next measure.
                        return { ...value, breaks: value.breaks.map(item => ({ ...item, pos: tr.mapping.map(item.pos) })) }
                    },
                },
                props: {
                    decorations(state) {
                        const value = key.getState(state)
                        if (!value) return null
                        const setup = options.setup()

                        return DecorationSet.create(state.doc, value.breaks
                            .filter(item => item.pos <= state.doc.content.size)
                            .map(item => Decoration.widget(item.pos, () => buildBreak(setup, item), {
                                side: -1,
                                key: `${value.version}-${item.page}-${item.filler}`,
                            })))
                    },
                },
                view(view) {
                    let frame = 0
                    let lastSignature = ''

                    const relayout = (force = false) => {
                        cancelAnimationFrame(frame)
                        frame = requestAnimationFrame(() => {
                            if (view.isDestroyed) return
                            const { breaks, layout } = measure(view)
                            const signature = JSON.stringify([breaks, options.setup()])
                            options.onLayout(layout)
                            if (!force && signature === lastSignature) return
                            lastSignature = signature
                            view.dispatch(view.state.tr.setMeta(key, { breaks }).setMeta('addToHistory', false))
                        })
                    }

                    const onRefresh = () => relayout(true)
                    view.dom.addEventListener('pagination:refresh', onRefresh)
                    window.addEventListener('resize', onRefresh)
                    relayout(true)

                    return {
                        update: () => relayout(),
                        destroy() {
                            cancelAnimationFrame(frame)
                            view.dom.removeEventListener('pagination:refresh', onRefresh)
                            window.removeEventListener('resize', onRefresh)
                        },
                    }
                },
            }),
        ]
    },
})
