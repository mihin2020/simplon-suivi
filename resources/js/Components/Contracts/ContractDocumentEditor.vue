<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { Pagination, areaMm, type PageSetup, type PaginationState } from './pagination'
import { contractFontStack, loadContractFont, type ContractFontOption } from '@/composables/useContractFont'

/**
 * Word-like editor for a contract body, laid out on A4 pages: margins, the
 * linked header/footer on the pages that carry them, and a page break when
 * the text overflows. Formatting is limited to what the PDF and the HTML
 * sanitiser keep.
 */
const props = withDefaults(defineProps<{
    modelValue: string
    /** Document title, shown at the top of the first page as it prints in the PDF. */
    title?: string
    headerMm?: number | null
    footerMm?: number | null
    headerUrl?: string | null
    footerUrl?: string | null
    firstPageOnly?: boolean
    /** Signature block, printed after the text. */
    signatories?: { left_title: string; left_name: string; right_title: string; right_name: string } | null
    /** Font the contract prints in; the editor shows the same one. */
    font?: ContractFontOption | null
    /** Line-height factor ("1.5"), as printed. */
    lineSpacing?: string
}>(), { title: '', headerMm: 0, footerMm: 0, headerUrl: null, footerUrl: null, firstPageOnly: false, signatories: null, font: null, lineSpacing: '1.5' })

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const setup = computed<PageSetup>(() => ({
    headerMm: props.headerMm ?? 0,
    footerMm: props.footerMm ?? 0,
    headerUrl: props.headerUrl,
    footerUrl: props.footerUrl,
    firstPageOnly: props.firstPageOnly,
}))

const titleEl = ref<HTMLElement | null>(null)
const layout = ref<PaginationState>({ pages: 1, lastFiller: 0 })

// Bumped on every transaction so the toolbar re-reads the active marks.
const tick = ref(0)

const editor = useEditor({
    extensions: [
        StarterKit.configure({ heading: { levels: [2, 3] }, code: false, codeBlock: false, strike: false, link: false }),
        Pagination.configure({
            setup: () => setup.value,
            // Title height plus its bottom margin, both part of page 1.
            leadingHeight: () => {
                const el = titleEl.value
                if (!el) return 0

                return el.getBoundingClientRect().height + (parseFloat(getComputedStyle(el).marginBottom) || 0)
            },
            onLayout: state => { layout.value = state },
        }),
    ],
    content: props.modelValue || '',
    onUpdate: ({ editor: instance }) => emit('update:modelValue', instance.getHTML()),
    onTransaction: () => { tick.value++ },
})

watch(() => props.modelValue, value => {
    if (editor.value && editor.value.getHTML() !== value) editor.value.commands.setContent(value, { emitUpdate: false })
})

const relayout = () => editor.value?.view.dom.dispatchEvent(new Event('pagination:refresh'))

// The page geometry depends on the title, the letterhead and the spacing: lay out again.
watch([setup, () => props.title, () => props.lineSpacing], async () => {
    await nextTick()
    relayout()
}, { deep: true })

// Another font changes every line height: lay out again once it has loaded.
const fontStack = computed(() => contractFontStack(props.font))
watch(() => props.font, async font => {
    loadContractFont(font)
    await nextTick()
    relayout()
    await document.fonts?.ready
    relayout()
}, { immediate: true })

onBeforeUnmount(() => editor.value?.destroy())

const firstHeader = computed(() => ({ height: `${areaMm(setup.value, 'header', 1)}mm`, band: setup.value.headerMm > 0 }))
const lastFooter = computed(() => {
    const page = layout.value.pages

    return {
        height: `${areaMm(setup.value, 'footer', page)}mm`,
        band: setup.value.footerMm > 0 && (!setup.value.firstPageOnly || page === 1),
    }
})

const active = (name: string, attributes?: Record<string, unknown>) => {
    void tick.value

    return editor.value?.isActive(name, attributes) ?? false
}

const blockType = () => {
    if (active('heading', { level: 2 })) return 'h2'
    if (active('heading', { level: 3 })) return 'h3'

    return 'p'
}

const setBlock = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value
    const chain = editor.value?.chain().focus()
    if (!chain) return
    if (value === 'p') chain.setParagraph().run()
    else chain.setHeading({ level: value === 'h2' ? 2 : 3 }).run()
}

const can = (action: 'undo' | 'redo') => {
    void tick.value

    return editor.value?.can()[action]() ?? false
}
</script>

<template>
    <div class="doc-editor">
        <div class="toolbar">
            <button type="button" class="tb" title="Annuler (Ctrl+Z)" :disabled="!can('undo')" @click="editor?.chain().focus().undo().run()">
                <span class="material-symbols-outlined">undo</span>
            </button>
            <button type="button" class="tb" title="Rétablir (Ctrl+Y)" :disabled="!can('redo')" @click="editor?.chain().focus().redo().run()">
                <span class="material-symbols-outlined">redo</span>
            </button>
            <span class="sep" />
            <select class="block-select" :value="blockType()" title="Style de paragraphe" @change="setBlock">
                <option value="p">Paragraphe</option>
                <option value="h2">Titre d'article</option>
                <option value="h3">Sous-titre</option>
            </select>
            <span class="sep" />
            <button type="button" class="tb" :class="{ on: active('bold') }" title="Gras (Ctrl+B)" @click="editor?.chain().focus().toggleBold().run()">
                <span class="material-symbols-outlined">format_bold</span>
            </button>
            <button type="button" class="tb" :class="{ on: active('italic') }" title="Italique (Ctrl+I)" @click="editor?.chain().focus().toggleItalic().run()">
                <span class="material-symbols-outlined">format_italic</span>
            </button>
            <button type="button" class="tb" :class="{ on: active('underline') }" title="Souligné (Ctrl+U)" @click="editor?.chain().focus().toggleUnderline().run()">
                <span class="material-symbols-outlined">format_underlined</span>
            </button>
            <span class="sep" />
            <button type="button" class="tb" :class="{ on: active('bulletList') }" title="Liste à puces" @click="editor?.chain().focus().toggleBulletList().run()">
                <span class="material-symbols-outlined">format_list_bulleted</span>
            </button>
            <button type="button" class="tb" :class="{ on: active('orderedList') }" title="Liste numérotée" @click="editor?.chain().focus().toggleOrderedList().run()">
                <span class="material-symbols-outlined">format_list_numbered</span>
            </button>
            <button type="button" class="tb" :class="{ on: active('blockquote') }" title="Citation / retrait" @click="editor?.chain().focus().toggleBlockquote().run()">
                <span class="material-symbols-outlined">format_quote</span>
            </button>
            <span class="pages">{{ layout.pages }} page{{ layout.pages > 1 ? 's' : '' }} A4</span>
        </div>

        <div class="desk">
            <div class="sheet" :style="{ fontFamily: fontStack, lineHeight: lineSpacing }">
                <!-- Top of page 1 -->
                <div class="page-area page-area-header" :style="{ height: firstHeader.height }">
                    <img v-if="firstHeader.band && headerUrl" :src="headerUrl" alt="" draggable="false" :style="{ height: `${setup.headerMm}mm` }" />
                </div>

                <p v-if="title" ref="titleEl" class="doc-title">{{ title }}</p>
                <EditorContent :editor="editor" />
                <div v-if="signatories" class="signatures" title="Modifiable dans « Bloc de signatures »">
                    <div><strong>{{ signatories.left_title }}</strong><strong class="sign-name">{{ signatories.left_name }}</strong></div>
                    <div class="right"><strong>{{ signatories.right_title }}</strong><strong class="sign-name">{{ signatories.right_name }}</strong></div>
                </div>

                <!-- Rest of the last page, then its bottom edge -->
                <div :style="{ height: `${layout.lastFiller}px` }" />
                <div class="page-area page-area-footer" :style="{ height: lastFooter.height }">
                    <img v-if="lastFooter.band && footerUrl" :src="footerUrl" alt="" draggable="false" :style="{ height: `${setup.footerMm}mm` }" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.doc-editor { border: 1px solid #dfe4ea; border-radius: 12px; overflow: hidden; background: #e9edf1; }
.toolbar { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; gap: 2px; flex-wrap: wrap; padding: 6px 10px; background: #fff; border-bottom: 1px solid #dfe4ea; }
.tb { display: inline-flex; padding: 6px; border: none; border-radius: 6px; background: transparent; color: #374151; cursor: pointer; }
.tb .material-symbols-outlined { font-size: 20px; }
.tb:hover:not(:disabled) { background: #f1f3f5; }
.tb.on { background: #1F3A4D; color: #fff; }
.tb:disabled { opacity: 0.35; cursor: not-allowed; }
.sep { width: 1px; height: 22px; background: #e0e3e5; margin: 0 6px; }
.block-select { padding: 6px 8px; border: 1px solid #e0e3e5; border-radius: 6px; font-size: 13px; color: #374151; background: #fff; }
.pages { margin-left: auto; font-size: 12px; font-weight: 600; color: #515f74; }
.desk { max-height: 75vh; overflow: auto; padding: 24px 16px; }

/* The sheet mirrors the PDF (resources/views/pdfs/contract.blade.php): A4
   pages, 20 mm side margins, same font sizes and spacing. Keep both in sync.
   Page edges and breaks are drawn by pagination.ts. */
.sheet { width: 210mm; margin: 0 auto; padding: 0 20mm; background: #fff; box-shadow: 0 1px 8px rgba(0, 0, 0, 0.18); font-family: 'DejaVu Serif', Georgia, 'Times New Roman', serif; font-size: 11pt; line-height: 1.5; color: #111827; }
.doc-title { text-align: center; font-size: 14pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5pt; line-height: 1.3; margin: 0 0 18pt; text-decoration: underline; }
.signatures { display: grid; grid-template-columns: 1fr 1fr; margin-top: 28pt; user-select: none; }
.signatures > div { display: flex; flex-direction: column; }
.signatures .right { text-align: right; }
.sign-name { padding-top: 52pt; }
.sheet :deep(.ProseMirror) { outline: none; min-height: 40mm; }
.sheet :deep(p) { margin: 0 0 8pt; text-align: justify; }
.sheet :deep(h2) { font-size: 11pt; font-weight: 700; text-decoration: underline; margin: 14pt 0 6pt; }
.sheet :deep(h3) { font-size: 11pt; font-weight: 700; margin: 10pt 0 4pt; }
.sheet :deep(ul) { list-style: disc; margin: 0 0 8pt; padding-left: 18pt; }
.sheet :deep(ol) { list-style: decimal; margin: 0 0 8pt; padding-left: 18pt; }
.sheet :deep(li) { margin-bottom: 3pt; text-align: justify; }
.sheet :deep(li p) { margin: 0; }
.sheet :deep(blockquote) { margin: 0 0 8pt 14pt; }
.sheet :deep(strong) { font-weight: 700; }
.sheet :deep(em) { font-style: italic; }
.sheet :deep(u) { text-decoration: underline; }

/* Page edges span the full sheet width, over the side margins. */
.sheet :deep(.page-area), .page-area { margin: 0 -20mm; text-align: center; overflow: hidden; user-select: none; display: flex; justify-content: center; }
.sheet :deep(.page-area-header), .page-area-header { align-items: flex-start; }
.sheet :deep(.page-area-footer), .page-area-footer { align-items: flex-end; }
.sheet :deep(.page-area img), .page-area img { display: block; max-width: 210mm; }
.sheet :deep(.page-break) { user-select: none; cursor: default; }
.sheet :deep(.page-gap) { margin: 0 -20mm; height: 28px; background: #e9edf1; box-shadow: inset 0 6px 6px -6px rgba(0, 0, 0, 0.25), inset 0 -6px 6px -6px rgba(0, 0, 0, 0.25); font-family: system-ui, sans-serif; font-size: 10px; line-height: 28px; text-align: center; color: #868e96; }
</style>
