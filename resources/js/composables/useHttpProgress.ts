import { ref } from 'vue'

/** Laravel's XSRF cookie, sent back as a header on fetch() requests (axios does it on its own). */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)

    return match ? decodeURIComponent(match[1]) : ''
}

const jsonHeaders = () => ({
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': xsrfToken(),
})

async function errorMessage(response: Response, fallback: string): Promise<string> {
    try {
        const data = await response.json()
        const first = data?.errors ? (Object.values(data.errors)[0] as string[] | undefined)?.[0] : undefined

        return first ?? data?.message ?? fallback
    } catch {
        return fallback
    }
}

/**
 * POST a request answered as NDJSON (one JSON object per line): `progress`
 * events with a percentage, then `done` or `error`. Resolves with the `done`
 * event. Used for AI drafting, which takes tens of seconds.
 */
export async function postWithProgress<T extends Record<string, unknown>>(
    url: string,
    payload: Record<string, unknown>,
    onProgress: (percent: number) => void,
): Promise<T> {
    const response = await fetch(url, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload), credentials: 'same-origin' })

    if (!response.ok || !response.body) {
        throw new Error(await errorMessage(response, "La requête a échoué. Réessayez."))
    }

    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''

    for (;;) {
        const { done, value } = await reader.read()
        buffer += decoder.decode(value ?? new Uint8Array(), { stream: !done })

        let newline: number
        while ((newline = buffer.indexOf('\n')) !== -1) {
            const line = buffer.slice(0, newline).trim()
            buffer = buffer.slice(newline + 1)
            if (!line) continue

            const event = JSON.parse(line)
            if (event.type === 'progress') onProgress(event.percent)
            else if (event.type === 'error') throw new Error(event.message)
            else if (event.type === 'done') {
                onProgress(100)
                return event as T
            }
        }

        if (done) break
    }

    throw new Error("La réponse a été interrompue. Réessayez.")
}

export type DownloadPhase = 'idle' | 'preparing' | 'downloading' | 'done' | 'error'

/**
 * Download a file with visible state: "preparing" while the server builds it,
 * then the real percentage received (from Content-Length), then "done".
 */
export function useDownload() {
    const phase = ref<DownloadPhase>('idle')
    const percent = ref<number | null>(null)
    const error = ref<string | null>(null)
    const activeUrl = ref<string | null>(null)

    async function download(url: string, fallbackName: string, options: { method?: 'GET' | 'POST'; body?: Record<string, unknown> } = {}): Promise<void> {
        if (phase.value === 'preparing' || phase.value === 'downloading') return

        activeUrl.value = url
        phase.value = 'preparing'
        percent.value = null
        error.value = null

        try {
            const response = await fetch(url, {
                method: options.method ?? 'GET',
                headers: options.body ? jsonHeaders() : { Accept: 'application/json, */*', 'X-Requested-With': 'XMLHttpRequest' },
                body: options.body ? JSON.stringify(options.body) : undefined,
                credentials: 'same-origin',
            })
            if (!response.ok || !response.body) throw new Error(await errorMessage(response, 'Le téléchargement a échoué.'))

            const total = Number(response.headers.get('Content-Length')) || 0
            const reader = response.body.getReader()
            const chunks: Uint8Array[] = []
            let received = 0
            phase.value = 'downloading'

            for (;;) {
                const { done, value } = await reader.read()
                if (done) break
                chunks.push(value)
                received += value.length
                percent.value = total ? Math.round((received / total) * 100) : null
            }

            const disposition = response.headers.get('Content-Disposition') ?? ''
            const name = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)?.[1]
            const blob = new Blob(chunks as BlobPart[], { type: response.headers.get('Content-Type') ?? 'application/octet-stream' })
            const link = document.createElement('a')
            link.href = URL.createObjectURL(blob)
            link.download = name ? decodeURIComponent(name) : fallbackName
            link.click()
            setTimeout(() => URL.revokeObjectURL(link.href), 10_000)

            phase.value = 'done'
            percent.value = 100
            setTimeout(() => { if (phase.value === 'done') phase.value = 'idle' }, 3000)
        } catch (e) {
            phase.value = 'error'
            error.value = e instanceof Error ? e.message : 'Le téléchargement a échoué.'
        }
    }

    const isBusy = (url?: string) => (phase.value === 'preparing' || phase.value === 'downloading') && (!url || activeUrl.value === url)

    return { phase, percent, error, activeUrl, download, isBusy }
}
