<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PhoneInput, { type PhoneCountry } from '@/Components/Forms/PhoneInput.vue'

interface Field {
    id: string
    type: string
    label: string
    help_text: string | null
    is_required: boolean
    options: Array<{ label: string; value: string }> | null
    settings: Record<string, unknown> | null
    is_answerable: boolean
}

interface PublicForm {
    id: string
    title: string
    description: string | null
    header_image_url?: string | null
    status: string
    status_label: string
    accepts_responses: boolean
    is_expired?: boolean
    project_name?: string
    formation_name?: string
    show_progress?: boolean
}

interface FormPage {
    title: string | null
    help: string | null
    fields: Field[]
}

const props = defineProps<{
    form: PublicForm
    fields: Field[]
    publicToken: string
    phoneCountries: PhoneCountry[]
}>()

const answers = reactive<Record<string, string | string[]>>({})
const fileInputs = reactive<Record<string, File | null>>({})
const focusedId = reactive<{ value: string | null }>({ value: null })
const scrollPercent = ref(0)
const pageRef = ref<HTMLElement | null>(null)
const currentPage = ref(0)
const pageError = ref<string | null>(null)

props.fields.forEach((f) => {
    if (!f.is_answerable) return
    if (f.type === 'multiple_choice') answers[f.id] = []
    else if (f.type === 'file') fileInputs[f.id] = null
    else if (f.type === 'phone') answers[f.id] = '+226 '
    else answers[f.id] = ''
})

const formData = useForm({
    answers,
    files: {} as Record<string, File | null>,
    website: '',
})

const pages = computed<FormPage[]>(() => {
    const hasSections = props.fields.some(f => f.type === 'section_header')
    if (!hasSections) {
        return [{ title: null, help: null, fields: props.fields }]
    }

    const result: FormPage[] = []
    let current: FormPage = { title: null, help: null, fields: [] }

    props.fields.forEach((f) => {
        if (f.type === 'section_header') {
            if (current.fields.length > 0 || current.title) {
                result.push(current)
            }
            current = { title: f.label, help: f.help_text, fields: [] }
            return
        }
        current.fields.push(f)
    })
    result.push(current)

    return result.filter(p => p.fields.length > 0 || p.title)
})

const activePage = computed(() => pages.value[currentPage.value] ?? pages.value[0])
const isLastPage = computed(() => currentPage.value >= pages.value.length - 1)
const isFirstPage = computed(() => currentPage.value <= 0)

const answerable = computed(() => props.fields.filter(f => f.is_answerable))

const filledCount = computed(() =>
    answerable.value.filter((f) => {
        if (f.type === 'file') return Boolean(fileInputs[f.id])
        const v = answers[f.id]
        if (Array.isArray(v)) return v.length > 0
        return String(v ?? '').trim() !== '' && String(v).trim() !== '+226'
    }).length
)

const progressPercent = computed(() => {
    if (answerable.value.length === 0) return Math.round(((currentPage.value + 1) / pages.value.length) * 100)
    return Math.round((filledCount.value / answerable.value.length) * 100)
})

const showProgress = computed(() => props.form.show_progress !== false)

const updateScrollProgress = () => {
    const el = pageRef.value
    if (!el) return
    const max = el.scrollHeight - el.clientHeight
    scrollPercent.value = max <= 0 ? 0 : Math.min(100, Math.round((el.scrollTop / max) * 100))
}

onMounted(() => {
    updateScrollProgress()
    pageRef.value?.addEventListener('scroll', updateScrollProgress, { passive: true })
    window.addEventListener('resize', updateScrollProgress)
})

onUnmounted(() => {
    pageRef.value?.removeEventListener('scroll', updateScrollProgress)
    window.removeEventListener('resize', updateScrollProgress)
})

const onFile = (fieldId: string, e: Event) => {
    fileInputs[fieldId] = (e.target as HTMLInputElement).files?.[0] ?? null
}

const toggleMulti = (fieldId: string, value: string, checked: boolean) => {
    const current = Array.isArray(answers[fieldId]) ? [...answers[fieldId] as string[]] : []
    answers[fieldId] = checked
        ? [...current, value]
        : current.filter(v => v !== value)
}

const scaleRange = (field: Field) => {
    const min = Number(field.settings?.min ?? 1)
    const max = Number(field.settings?.max ?? 5)
    return Array.from({ length: max - min + 1 }, (_, i) => min + i)
}

const fieldFilled = (f: Field) => {
    if (!f.is_answerable) return true
    if (!f.is_required) return true
    if (f.type === 'file') return Boolean(fileInputs[f.id])
    const v = answers[f.id]
    if (Array.isArray(v)) return v.length > 0
    return String(v ?? '').trim() !== '' && String(v).trim() !== '+226'
}

const validateCurrentPage = () => {
    const missing = (activePage.value?.fields ?? []).filter(f => f.is_required && !fieldFilled(f))
    if (missing.length) {
        pageError.value = 'Complétez les champs obligatoires avant de continuer.'
        return false
    }
    pageError.value = null
    return true
}

const nextPage = () => {
    if (!validateCurrentPage()) return
    if (!isLastPage.value) {
        currentPage.value += 1
        pageRef.value?.scrollTo({ top: 0, behavior: 'smooth' })
    }
}

const prevPage = () => {
    pageError.value = null
    if (!isFirstPage.value) {
        currentPage.value -= 1
        pageRef.value?.scrollTo({ top: 0, behavior: 'smooth' })
    }
}

const submit = () => {
    if (!validateCurrentPage()) return
    formData.answers = answers
    formData.files = { ...fileInputs }
    formData.post(`/f/${props.publicToken}`, { forceFormData: true })
}

const fieldError = (id: string) =>
    (formData.errors as Record<string, string>)[`answers.${id}`]
        ?? (formData.errors as Record<string, string>)[`files.${id}`]
</script>

<template>
    <Head :title="form.title" />
    <div ref="pageRef" class="gf-page">
        <div
            v-if="showProgress && form.accepts_responses"
            class="gf-scroll-bar"
            role="progressbar"
            :aria-valuenow="scrollPercent"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div class="gf-scroll-fill" :style="{ width: `${scrollPercent}%` }" />
        </div>

        <aside v-if="showProgress && form.accepts_responses" class="gf-side-progress">
            <div class="gf-side-track">
                <div class="gf-side-fill" :style="{ height: `${progressPercent}%` }" />
            </div>
            <span class="gf-side-pct">{{ progressPercent }}%</span>
        </aside>

        <div class="gf-wrap">
            <section class="gf-card gf-header-card">
                <div v-if="form.header_image_url" class="gf-banner">
                    <img :src="form.header_image_url" :alt="form.title" />
                </div>
                <div v-else class="gf-banner-fallback" />
                <div class="gf-header-body">
                    <h1>{{ form.title }}</h1>
                    <p v-if="form.project_name || form.formation_name" class="gf-meta">
                        {{ form.project_name }}<span v-if="form.formation_name"> · {{ form.formation_name }}</span>
                    </p>
                    <p v-if="form.description" class="gf-desc">{{ form.description }}</p>
                    <p v-if="pages.length > 1 && form.accepts_responses" class="gf-page-indicator">
                        Page {{ currentPage + 1 }} / {{ pages.length }}
                    </p>
                </div>
            </section>

            <div v-if="form.is_expired" class="gf-card gf-closed">
                <span class="material-symbols-outlined">schedule</span>
                <h2>Temps écoulé</h2>
                <p>La date limite de ce formulaire est dépassée. Les candidatures ne sont plus acceptées.</p>
            </div>

            <div v-else-if="!form.accepts_responses" class="gf-card gf-closed">
                <span class="material-symbols-outlined">lock</span>
                <p>Ce formulaire n’accepte plus les candidatures ({{ form.status_label }}).</p>
            </div>

            <form v-else class="gf-form" @submit.prevent="isLastPage ? submit() : nextPage()">
                <div class="hp" aria-hidden="true">
                    <label for="website">Site web</label>
                    <input id="website" v-model="formData.website" type="text" tabindex="-1" autocomplete="off" />
                </div>

                <div v-if="activePage?.title" class="gf-card gf-section">
                    <h2>{{ activePage.title }}</h2>
                    <p v-if="activePage.help">{{ activePage.help }}</p>
                </div>

                <template v-for="field in activePage?.fields || []" :key="field.id">
                    <div v-if="field.type === 'paragraph'" class="gf-card gf-paragraph">
                        {{ field.label }}
                    </div>

                    <div
                        v-else
                        class="gf-card gf-field"
                        :class="{ focused: focusedId.value === field.id }"
                        @focusin="focusedId.value = field.id"
                    >
                        <label :for="field.id">
                            {{ field.label }}
                            <span v-if="field.is_required" class="req">*</span>
                        </label>
                        <p v-if="field.help_text" class="help">{{ field.help_text }}</p>

                        <textarea
                            v-if="field.type === 'long_text'"
                            :id="field.id"
                            v-model="answers[field.id] as string"
                            rows="4"
                            :required="field.is_required"
                        />

                        <div v-else-if="field.type === 'single_choice'" class="choices">
                            <label v-for="opt in field.options || []" :key="opt.value" class="choice">
                                <input v-model="answers[field.id]" type="radio" :value="opt.value" :required="field.is_required" />
                                {{ opt.label }}
                            </label>
                        </div>

                        <select
                            v-else-if="field.type === 'dropdown'"
                            :id="field.id"
                            v-model="answers[field.id] as string"
                            :required="field.is_required"
                        >
                            <option value="" disabled>Choisir…</option>
                            <option v-for="opt in field.options || []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>

                        <div v-else-if="field.type === 'multiple_choice'" class="choices">
                            <label v-for="opt in field.options || []" :key="opt.value" class="choice">
                                <input
                                    type="checkbox"
                                    :value="opt.value"
                                    :checked="(answers[field.id] as string[]).includes(opt.value)"
                                    @change="toggleMulti(field.id, opt.value, ($event.target as HTMLInputElement).checked)"
                                />
                                {{ opt.label }}
                            </label>
                        </div>

                        <div v-else-if="field.type === 'linear_scale'" class="scale">
                            <span class="scale-label">{{ field.settings?.min_label }}</span>
                            <label v-for="n in scaleRange(field)" :key="n" class="scale-item">
                                <input v-model="answers[field.id]" type="radio" :value="String(n)" :required="field.is_required" />
                                <span>{{ n }}</span>
                            </label>
                            <span class="scale-label">{{ field.settings?.max_label }}</span>
                        </div>

                        <label v-else-if="field.type === 'file'" :for="field.id" class="file-drop">
                            <span class="material-symbols-outlined">upload_file</span>
                            <span class="file-drop-title">
                                {{ fileInputs[field.id]?.name || 'Choisir un fichier à envoyer' }}
                            </span>
                            <span class="file-drop-hint">PDF, JPG, PNG ou Word — max 5 Mo</span>
                            <input
                                :id="field.id"
                                type="file"
                                class="sr-only"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                :required="field.is_required"
                                @change="onFile(field.id, $event)"
                            />
                        </label>

                        <PhoneInput
                            v-else-if="field.type === 'phone'"
                            :id="field.id"
                            v-model="answers[field.id] as string"
                            :countries="phoneCountries"
                            :required="field.is_required"
                        />

                        <input
                            v-else
                            :id="field.id"
                            v-model="answers[field.id] as string"
                            :type="field.type === 'email' ? 'email' : field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'"
                            :required="field.is_required"
                        />

                        <p v-if="fieldError(field.id)" class="err">{{ fieldError(field.id) }}</p>
                    </div>
                </template>

                <p v-if="pageError || formData.errors.form || formData.errors.email" class="err center">
                    {{ pageError || formData.errors.form || formData.errors.email }}
                </p>

                <div class="gf-nav-row">
                    <button v-if="!isFirstPage" type="button" class="btn-nav" @click="prevPage">Précédent</button>
                    <div class="spacer" />
                    <button
                        v-if="!isLastPage"
                        type="button"
                        class="submit"
                        @click="nextPage"
                    >
                        Suivant
                    </button>
                    <button
                        v-else
                        type="submit"
                        class="submit"
                        :disabled="formData.processing"
                    >
                        <span v-if="formData.processing" class="spinner" />
                        {{ formData.processing ? 'Envoi…' : 'Envoyer' }}
                    </button>
                </div>
            </form>

            <p class="footer">© Simplon Burkina Faso</p>
        </div>
    </div>
</template>

<style scoped>
.gf-page {
    --pink: #E5004C;
    --navy: #1F3A4D;
    --bg: #f0ebf8;
    height: 100dvh;
    max-height: 100dvh;
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    background: var(--bg);
    padding: 1.5rem 1rem 3rem;
    position: relative;
    box-sizing: border-box;
}
.gf-scroll-bar {
    position: fixed; top: 0; left: 0; right: 0; height: 5px;
    background: rgba(31, 58, 77, 0.12); z-index: 50;
}
.gf-scroll-fill { height: 100%; background: var(--pink); transition: width 0.1s linear; }
.gf-wrap {
    width: 100%; max-width: 640px; margin: 0 auto;
    display: flex; flex-direction: column; gap: 12px;
}
.gf-side-progress { display: none; }
@media (min-width: 1100px) {
    .gf-side-progress {
        display: flex; flex-direction: column; align-items: center; gap: 8px;
        position: fixed; left: calc(50% - 320px - 56px); top: 50%;
        transform: translateY(-50%); z-index: 5;
    }
    .gf-side-track {
        width: 6px; height: 180px; background: #ddd6fe; border-radius: 99px;
        overflow: hidden; display: flex; flex-direction: column-reverse;
    }
    .gf-side-fill { width: 100%; background: var(--pink); transition: height 0.25s ease; }
    .gf-side-pct { font-size: 11px; font-weight: 700; color: var(--navy); }
}
.gf-card {
    background: #fff; border-radius: 8px; border: 1px solid #dadce0;
    box-shadow: 0 1px 2px rgba(60, 64, 67, 0.08); overflow: hidden;
    border-left: 6px solid transparent;
}
.gf-header-card { border-left-color: var(--pink); padding: 0; }
.gf-banner { width: 100%; aspect-ratio: 4 / 1; background: #e8eaed; }
.gf-banner img { width: 100%; height: 100%; object-fit: cover; display: block; }
.gf-banner-fallback { height: 10px; background: linear-gradient(90deg, var(--pink), var(--navy)); }
.gf-header-body { padding: 1.25rem 1.35rem 1.1rem; }
.gf-header-body h1 { font-size: 1.7rem; font-weight: 700; color: #202124; line-height: 1.25; }
.gf-meta { margin-top: 0.35rem; color: #5f6368; font-size: 0.9rem; }
.gf-desc { margin-top: 0.75rem; color: #3c4043; white-space: pre-wrap; font-size: 0.95rem; }
.gf-page-indicator {
    margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #e8eaed;
    font-size: 0.85rem; color: #5f6368; font-weight: 600;
}
.req { color: var(--pink); }
.gf-closed {
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
    padding: 1.5rem; text-align: center; color: #5f6368; border-left-color: #9aa0a6;
}
.gf-closed h2 { font-size: 1.2rem; font-weight: 700; color: var(--navy); margin: 0; }
.gf-form { display: flex; flex-direction: column; gap: 12px; }
.gf-section { padding: 1.1rem 1.35rem; border-left-color: var(--navy); }
.gf-section h2 { font-size: 1.15rem; font-weight: 700; color: var(--navy); }
.gf-section p, .gf-paragraph { color: #5f6368; font-size: 0.95rem; white-space: pre-wrap; }
.gf-paragraph { padding: 1.1rem 1.35rem; }
.gf-field { padding: 1.1rem 1.35rem; overflow: visible; }
.gf-field.focused { border-left-color: var(--pink); box-shadow: 0 1px 3px rgba(229, 0, 76, 0.12); }
.gf-field label { display: block; font-weight: 500; font-size: 0.98rem; color: #202124; margin-bottom: 0.45rem; }
.help { font-size: 0.8rem; color: #5f6368; margin-bottom: 0.45rem; }
.gf-field input[type="text"],
.gf-field input[type="email"],
.gf-field input[type="number"],
.gf-field input[type="date"],
.gf-field textarea,
.gf-field select {
    width: 100%; border: none; border-bottom: 1px solid #dadce0; border-radius: 0;
    padding: 0.55rem 0; font-size: 1rem; background: transparent; outline: none;
}
.gf-field input:focus, .gf-field textarea:focus, .gf-field select:focus {
    border-bottom-color: var(--pink); box-shadow: 0 1px 0 var(--pink);
}
.choices { display: flex; flex-direction: column; gap: 0.55rem; }
.choice { display: flex; align-items: center; gap: 0.55rem; font-weight: 400 !important; margin: 0 !important; }
.scale { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem; }
.scale-item { display: flex; flex-direction: column; align-items: center; gap: 0.2rem; font-weight: 500 !important; font-size: 0.85rem; margin: 0 !important; }
.scale-label { font-size: 0.8rem; color: #5f6368; }
.file-drop {
    display: flex; flex-direction: column; align-items: center; gap: 0.35rem;
    padding: 1rem; border: 2px dashed #dadce0; border-radius: 8px; background: #f8f9fa;
    cursor: pointer; text-align: center;
}
.file-drop:hover { border-color: var(--pink); background: #fff5f8; }
.file-drop .material-symbols-outlined { font-size: 28px; color: var(--pink); }
.file-drop-title { font-size: 0.9rem; font-weight: 600; color: var(--navy); }
.file-drop-hint { font-size: 0.75rem; color: #5f6368; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
.gf-nav-row { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.25rem; }
.spacer { flex: 1; }
.btn-nav {
    border: 1px solid #dadce0; background: #fff; color: #1F3A4D; border-radius: 6px;
    padding: 0.65rem 1.1rem; font-weight: 600; cursor: pointer; font-family: inherit;
}
.submit {
    background: var(--pink); color: white; border: none; border-radius: 6px;
    padding: 0.7rem 1.5rem; font-weight: 600; cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.5rem;
}
.submit:disabled { opacity: 0.6; cursor: not-allowed; }
.spinner {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.err { color: #d93025; font-size: 0.85rem; margin-top: 0.35rem; }
.err.center { text-align: center; }
.footer { text-align: center; margin-top: 0.5rem; color: #5f6368; font-size: 0.8rem; }
.hp { position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden; }
</style>
