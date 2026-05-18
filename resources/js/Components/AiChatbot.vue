<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import axios from 'axios'
import { chatStore } from '@/stores/chatStore'

const isLoading = ref(false)
const input = ref('')
const messagesContainer = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const isConfigured = ref(true)

// Alias sur le store persistant
const messages = chatStore.messages
const isOpen = ref(chatStore.isOpen)

// Synchroniser isOpen → store à chaque changement
const toggle = () => {
    isOpen.value = !isOpen.value
    chatStore.isOpen = isOpen.value
    if (isOpen.value) {
        if (messages.length === 0) {
            addWelcomeMessage()
        }
        nextTick(() => {
            inputRef.value?.focus()
            scrollToBottom()
        })
    }
}

const addWelcomeMessage = () => {
    messages.push({
        role: 'assistant',
        content: 'Bonjour ! Je suis votre assistant Simplon. Je peux vous aider à naviguer dans l\'application, trouver des informations sur les apprenants, formations, projets, et bien plus encore. Comment puis-je vous aider ?',
        timestamp: new Date(),
    })
}

const scrollToBottom = async () => {
    await nextTick()
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
}

const send = async () => {
    const text = input.value.trim()
    if (!text || isLoading.value) return

    messages.push({ role: 'user', content: text, timestamp: new Date() })
    input.value = ''
    isLoading.value = true
    await scrollToBottom()

    // Build history (last 10 exchanges, excluding welcome)
    const history = messages
        .slice(0, -1)
        .slice(-10)
        .map(m => ({ role: m.role, content: m.content }))

    try {
        const response = await axios.post('/chatbot/message', {
            message: text,
            history,
        })

        messages.push({
            role: 'assistant',
            content: response.data.reply,
            timestamp: new Date(),
        })
    } catch (error: any) {
        const msg = error.response?.status === 429
            ? 'Vous envoyez trop de messages. Veuillez patienter une minute.'
            : 'Une erreur est survenue. Veuillez réessayer.'

        messages.push({ role: 'assistant', content: msg, timestamp: new Date() })
    } finally {
        isLoading.value = false
        await scrollToBottom()
        inputRef.value?.focus()
    }
}

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        send()
    }
}

const clearChat = () => {
    messages.splice(0, messages.length)
    addWelcomeMessage()
}

const renderContent = (content: string): string => {
    const lines = content.split('\n')
    const output: string[] = []
    let i = 0

    while (i < lines.length) {
        const line = lines[i]

        // Detect markdown table: line with | at start or contains |---|
        if (line.trim().startsWith('|') && lines[i + 1]?.trim().match(/^\|[\s|:-]+\|$/)) {
            // Table header
            const headers = parseTableRow(lines[i])
            i += 2 // skip separator row

            let rows = ''
            while (i < lines.length && lines[i].trim().startsWith('|')) {
                rows += `<tr>${parseTableRow(lines[i]).map(c => `<td>${renderInline(c)}</td>`).join('')}</tr>`
                i++
            }

            output.push(
                `<div class="ai-table-wrap"><table class="ai-table">` +
                `<thead><tr>${headers.map(h => `<th>${renderInline(h)}</th>`).join('')}</tr></thead>` +
                `<tbody>${rows}</tbody></table></div>`
            )
            continue
        }

        // Heading ###
        if (line.startsWith('### ')) {
            output.push(`<p class="ai-heading">${renderInline(line.slice(4))}</p>`)
            i++
            continue
        }

        // Heading **bold** standalone line
        if (line.startsWith('**') && line.endsWith('**') && line.length > 4) {
            output.push(`<p><strong>${renderInline(line.slice(2, -2))}</strong></p>`)
            i++
            continue
        }

        // List item
        if (line.trimStart().startsWith('- ')) {
            output.push(`<div class="ai-list-item">${renderInline(line.trimStart().slice(2))}</div>`)
            i++
            continue
        }

        // Horizontal rule
        if (line.trim() === '---') {
            output.push('<hr class="ai-hr">')
            i++
            continue
        }

        // Empty line
        if (line.trim() === '') {
            output.push('<br>')
            i++
            continue
        }

        // Normal line
        output.push(`<span>${renderInline(line)}</span><br>`)
        i++
    }

    return output.join('')
}

const parseTableRow = (line: string): string[] =>
    line.trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim())

const renderInline = (text: string): string =>
    text
        .replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g, '<a href="$2" target="_blank" class="ai-link">$1</a>')
        .replace(/\[([^\]]+)\]\((\/?[^)]+)\)/g, '<a href="$2" class="ai-link">$1</a>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')

onMounted(async () => {
    // Restaurer l'état ouvert/fermé depuis le store
    isOpen.value = chatStore.isOpen
    if (chatStore.isOpen && messages.length === 0) {
        addWelcomeMessage()
    }
    if (chatStore.isOpen) {
        nextTick(() => scrollToBottom())
    }

    try {
        const res = await axios.get('/chatbot/status')
        isConfigured.value = res.data.configured
    } catch {
        // ignore
    }
})
</script>

<template>
    <!-- Floating button -->
    <button
        @click="toggle"
        class="relative flex items-center justify-center w-9 h-9 rounded-full transition-all duration-200"
        :class="isOpen ? 'bg-primary text-white' : 'hover:bg-surface-container text-on-surface-variant hover:text-on-surface'"
        title="Assistant IA"
    >
        <span class="material-symbols-outlined" style="font-size: 22px;">
            {{ isOpen ? 'close' : 'smart_toy' }}
        </span>
        <!-- Dot if not configured -->
        <span
            v-if="!isConfigured"
            class="absolute top-0.5 right-0.5 w-2 h-2 rounded-full bg-amber-400 border border-white"
        ></span>
    </button>

    <!-- Chat panel -->
    <Transition name="chat-panel">
        <div
            v-if="isOpen"
            class="fixed bottom-4 right-4 w-[380px] h-[520px] bg-surface rounded-2xl shadow-2xl border border-surface-container-highest flex flex-col z-50 overflow-hidden"
        >
            <!-- Header -->
            <div class="bg-primary px-md py-sm flex items-center justify-between gap-sm shrink-0">
                <div class="flex items-center gap-sm">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white" style="font-size: 18px;">smart_toy</span>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-body-sm leading-tight">Assistant Simplon</p>
                        <p class="text-white/70 text-[11px] leading-tight">Propulsé par l'IA</p>
                    </div>
                </div>
                <div class="flex items-center gap-xs">
                    <button
                        @click="clearChat"
                        class="text-white/70 hover:text-white transition-colors p-xs rounded"
                        title="Nouvelle conversation"
                    >
                        <span class="material-symbols-outlined" style="font-size: 18px;">refresh</span>
                    </button>
                    <button
                        @click="toggle"
                        class="text-white/70 hover:text-white transition-colors p-xs rounded"
                    >
                        <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                    </button>
                </div>
            </div>

            <!-- API key warning -->
            <div
                v-if="!isConfigured"
                class="bg-amber-50 border-b border-amber-200 px-md py-xs flex items-center gap-sm"
            >
                <span class="material-symbols-outlined text-amber-600" style="font-size: 16px;">warning</span>
                <p class="text-amber-800 text-[11px]">
                    Clé API non configurée.
                    <a href="/configuration" class="font-semibold underline">Configurer</a>
                </p>
            </div>

            <!-- Messages -->
            <div
                ref="messagesContainer"
                class="flex-1 overflow-y-auto px-md py-sm flex flex-col gap-sm"
                style="scrollbar-width: thin;"
            >
                <div
                    v-for="(msg, i) in chatStore.messages"
                    :key="i"
                    class="flex"
                    :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <!-- Assistant avatar -->
                    <div v-if="msg.role === 'assistant'" class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center shrink-0 mr-xs mt-0.5">
                        <span class="material-symbols-outlined text-primary" style="font-size: 14px;">smart_toy</span>
                    </div>

                    <div
                        class="max-w-[80%] rounded-2xl px-sm py-xs text-body-sm leading-relaxed"
                        :class="msg.role === 'user'
                            ? 'bg-primary text-white rounded-tr-sm'
                            : 'bg-surface-container text-on-surface rounded-tl-sm'"
                        v-html="msg.role === 'assistant' ? renderContent(msg.content) : msg.content"
                    ></div>
                </div>

                <!-- Loading indicator -->
                <div v-if="isLoading" class="flex justify-start">
                    <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center shrink-0 mr-xs mt-0.5">
                        <span class="material-symbols-outlined text-primary" style="font-size: 14px;">smart_toy</span>
                    </div>
                    <div class="bg-surface-container rounded-2xl rounded-tl-sm px-sm py-xs flex items-center gap-xs">
                        <span class="typing-dot"></span>
                        <span class="typing-dot" style="animation-delay: 0.2s"></span>
                        <span class="typing-dot" style="animation-delay: 0.4s"></span>
                    </div>
                </div>
            </div>

            <!-- Input -->
            <div class="border-t border-surface-container-highest px-md py-sm shrink-0">
                <div class="flex items-end gap-sm bg-surface-container-low rounded-xl px-sm py-xs">
                    <input
                        ref="inputRef"
                        v-model="input"
                        @keydown="handleKeydown"
                        type="text"
                        placeholder="Posez votre question..."
                        class="flex-1 bg-transparent text-body-sm text-on-surface placeholder-on-surface-variant outline-none py-xs"
                        :disabled="isLoading"
                        maxlength="2000"
                    />
                    <button
                        @click="send"
                        :disabled="!input.trim() || isLoading"
                        class="w-8 h-8 rounded-full bg-primary flex items-center justify-center transition-opacity disabled:opacity-40 shrink-0"
                    >
                        <span class="material-symbols-outlined text-white" style="font-size: 18px;">send</span>
                    </button>
                </div>
                <p class="text-center text-[10px] text-on-surface-variant mt-xs">Entrée pour envoyer · Shift+Entrée pour sauter une ligne</p>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.chat-panel-enter-active,
.chat-panel-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.chat-panel-enter-from,
.chat-panel-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.97);
}

.typing-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    background: #94a3b8;
    border-radius: 50%;
    animation: typing 1s infinite;
}

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-4px); opacity: 1; }
}
</style>

<style>
.ai-link {
    color: #E5004C;
    text-decoration: underline;
    font-weight: 500;
}
.ai-link:hover { opacity: 0.8; }

.ai-table-wrap {
    overflow-x: auto;
    margin: 6px 0;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.ai-table {
    border-collapse: collapse;
    font-size: 12px;
    width: 100%;
    min-width: 300px;
}
.ai-table th {
    background: #f1f5f9;
    color: #334155;
    font-weight: 600;
    padding: 6px 10px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.ai-table td {
    padding: 5px 10px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: top;
}
.ai-table tr:last-child td { border-bottom: none; }
.ai-table tr:hover td { background: #f8fafc; }

.ai-heading {
    font-weight: 700;
    font-size: 13px;
    color: #1e293b;
    margin: 8px 0 4px;
}
.ai-list-item {
    padding-left: 12px;
    position: relative;
    margin: 2px 0;
}
.ai-list-item::before {
    content: '•';
    position: absolute;
    left: 2px;
    color: #E5004C;
}
.ai-hr {
    border: none;
    border-top: 1px solid #e2e8f0;
    margin: 8px 0;
}
</style>
