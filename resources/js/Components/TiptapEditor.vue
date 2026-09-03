<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Underline from '@tiptap/extension-underline'

const props = withDefaults(defineProps<{
    modelValue: string
    minHeight?: string
    placeholder?: string
}>(), {
    minHeight: '120px',
    placeholder: '',
})
const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const editor = useEditor({
    extensions: [StarterKit, Link, Underline],
    content: props.modelValue || '',
    editorProps: {
        attributes: {
            class: 'tiptap-body',
            'data-placeholder': props.placeholder,
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML())
    },
})

watch(() => props.modelValue, (value) => {
    const isSame = editor.value?.getHTML() === value
    if (!isSame) {
        editor.value?.commands.setContent(value, false)
    }
})

onBeforeUnmount(() => {
    editor.value?.destroy()
})
</script>

<template>
    <div class="border border-surface-container-highest rounded-lg overflow-hidden bg-surface">
        <div class="flex items-center gap-xs px-sm py-xs bg-surface-container-low border-b border-surface-container-highest flex-wrap">
            <button type="button" @click="editor?.chain().focus().toggleBold().run()" :class="{ 'bg-surface-container-high': editor?.isActive('bold') }" class="p-xs rounded hover:bg-surface-container-high transition-colors" title="Gras">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_bold</span>
            </button>
            <button type="button" @click="editor?.chain().focus().toggleItalic().run()" :class="{ 'bg-surface-container-high': editor?.isActive('italic') }" class="p-xs rounded hover:bg-surface-container-high transition-colors" title="Italique">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_italic</span>
            </button>
            <button type="button" @click="editor?.chain().focus().toggleUnderline().run()" :class="{ 'bg-surface-container-high': editor?.isActive('underline') }" class="p-xs rounded hover:bg-surface-container-high transition-colors" title="Souligné">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_underlined</span>
            </button>
            <div class="w-px h-4 bg-surface-container-highest"></div>
            <button type="button" @click="editor?.chain().focus().toggleBulletList().run()" :class="{ 'bg-surface-container-high': editor?.isActive('bulletList') }" class="p-xs rounded hover:bg-surface-container-high transition-colors" title="Puces">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_list_bulleted</span>
            </button>
            <button type="button" @click="editor?.chain().focus().toggleOrderedList().run()" :class="{ 'bg-surface-container-high': editor?.isActive('orderedList') }" class="p-xs rounded hover:bg-surface-container-high transition-colors" title="Liste numérotée">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_list_numbered</span>
            </button>
        </div>
        <EditorContent :editor="editor" class="p-sm text-body-sm text-on-surface" :style="{ minHeight }" />
    </div>
</template>

<style>
.ProseMirror:focus { outline: none; }
.ProseMirror { min-height: inherit; }
.ProseMirror ul { list-style-type: disc; padding-left: 1.25rem; }
.ProseMirror ol { list-style-type: decimal; padding-left: 1.25rem; }
.ProseMirror p { margin-bottom: 0.5rem; }
.ProseMirror p:last-child { margin-bottom: 0; }
.ProseMirror strong { font-weight: 700; }
.ProseMirror em { font-style: italic; }
.ProseMirror u { text-decoration: underline; }
</style>
