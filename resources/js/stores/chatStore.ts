import { reactive } from 'vue'

export interface ChatMessage {
    role: 'user' | 'assistant'
    content: string
    timestamp: Date
}

// Module-level singleton — survit aux navigations Inertia (le module n'est jamais rechargé)
export const chatStore = reactive<{
    messages: ChatMessage[]
    isOpen: boolean
}>({
    messages: [],
    isOpen: false,
})
