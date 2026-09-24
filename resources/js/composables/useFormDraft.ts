import { onMounted, onUnmounted, watch, type Ref } from 'vue'

type FormLike = {
    data: () => Record<string, unknown>
    defaults: (field?: string | Record<string, unknown>, value?: unknown) => unknown
    processing: boolean
    recentlySuccessful?: boolean
}

const SKIP_KEYS = new Set([
    'photo', 'cnib', 'cv', 'logo', 'file', 'files',
    'internship_contract_file', 'employment_contract_file',
])

function isFileLike(value: unknown): boolean {
    return typeof File !== 'undefined' && value instanceof File
}

function serializableData(data: Record<string, unknown>): Record<string, unknown> {
    const out: Record<string, unknown> = {}
    for (const [key, value] of Object.entries(data)) {
        if (SKIP_KEYS.has(key) || isFileLike(value) || Array.isArray(value) && value.some(isFileLike)) {
            continue
        }
        if (typeof value === 'boolean' || typeof value === 'number' || typeof value === 'string' || value === null) {
            out[key] = value
        }
    }
    return out
}

/**
 * Persist Inertia form fields in sessionStorage while the user navigates away.
 * Files are never stored. Draft is cleared after a successful submit.
 */
export function useFormDraft(storageKey: string, form: FormLike, enabled: Ref<boolean> | boolean = true) {
    const key = `form-draft:${storageKey}`
    let timer: ReturnType<typeof setTimeout> | null = null
    let restoring = false

    const isEnabled = () => (typeof enabled === 'boolean' ? enabled : enabled.value)

    const clearDraft = () => {
        try {
            sessionStorage.removeItem(key)
        } catch {
            // ignore quota / private mode
        }
    }

    const saveDraft = () => {
        if (!isEnabled() || restoring || form.processing) return
        try {
            const payload = serializableData(form.data())
            if (Object.keys(payload).length === 0) {
                clearDraft()
                return
            }
            sessionStorage.setItem(key, JSON.stringify({
                savedAt: Date.now(),
                data: payload,
            }))
        } catch {
            // ignore
        }
    }

    const restoreDraft = () => {
        if (!isEnabled()) return
        try {
            const raw = sessionStorage.getItem(key)
            if (!raw) return
            const parsed = JSON.parse(raw) as { data?: Record<string, unknown> }
            if (!parsed?.data || typeof parsed.data !== 'object') return

            restoring = true
            for (const [field, value] of Object.entries(parsed.data)) {
                if (field in form.data()) {
                    ;(form as Record<string, unknown>)[field] = value
                }
            }
            restoring = false
        } catch {
            restoring = false
        }
    }

    onMounted(() => {
        restoreDraft()
    })

    onUnmounted(() => {
        if (timer) clearTimeout(timer)
    })

    watch(
        () => form.data(),
        () => {
            if (timer) clearTimeout(timer)
            timer = setTimeout(saveDraft, 400)
        },
        { deep: true },
    )

    watch(
        () => form.recentlySuccessful,
        (ok) => {
            if (ok) clearDraft()
        },
    )

    return { clearDraft, saveDraft, restoreDraft }
}
