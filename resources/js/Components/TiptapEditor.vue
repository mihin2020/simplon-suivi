<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Underline from '@tiptap/extension-underline'

const props = defineProps<{ modelValue: string }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const editor = useEditor({
    extensions: [StarterKit, Link, Underline],
    content: props.modelValue,
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
        <div class="flex items-center gap-xs px-sm py-xs bg-surface-container-low border-b border-surface-container-highest">
            <button type="button" @click="editor?.chain().focus().toggleBold().run()" :class="{ 'bg-surface-container-high': editor?.isActive('bold') }" class="p-xs rounded hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_bold</span>
            </button>
            <button type="button" @click="editor?.chain().focus().toggleItalic().run()" :class="{ 'bg-surface-container-high': editor?.isActive('italic') }" class="p-xs rounded hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_italic</span>
            </button>
            <button type="button" @click="editor?.chain().focus().toggleUnderline().run()" :class="{ 'bg-surface-container-high': editor?.isActive('underline') }" class="p-xs rounded hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_underlined</span>
            </button>
            <div class="w-px h-4 bg-surface-container-highest"></div>
            <button type="button" @click="editor?.chain().focus().toggleBulletList().run()" :class="{ 'bg-surface-container-high': editor?.isActive('bulletList') }" class="p-xs rounded hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_list_bulleted</span>
            </button>
            <button type="button" @click="editor?.chain().focus().toggleOrderedList().run()" :class="{ 'bg-surface-container-high': editor?.isActive('orderedList') }" class="p-xs rounded hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">format_list_numbered</span>
            </button>
        </div>
        <EditorContent :editor="editor" class="p-sm min-h-[120px] text-body-sm text-on-surface" />
    </div>
</template>

<style>
.ProseMirror:focus { outline: none; }
.ProseMirror ul { list-style-type: disc; padding-left: 1.25rem; }
.ProseMirror ol { list-style-type: decimal; padding-left: 1.25rem; }
.ProseMirror p { margin-bottom: 0.5rem; }
</style>
