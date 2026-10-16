import { onBeforeUnmount, ref } from 'vue'
import { router } from '@inertiajs/vue3'

/** Below this, the answer arrives before a skeleton would even be noticed: showing one would only flicker. */
const SHOW_AFTER_MS = 180

/**
 * True while the current page reloads itself with other parameters - a
 * search, a filter, another page of results. Not for navigations to another
 * page (the top progress bar covers those), nor for silent refreshes of the
 * same URL (polling).
 */
export function useListLoading() {
    const loading = ref(false)
    let timer: ReturnType<typeof setTimeout> | undefined

    const isSamePageWithOtherParameters = (url: URL) => {
        const current = new URL(window.location.href)

        return url.pathname === current.pathname && url.search !== current.search
    }

    const offStart = router.on('start', event => {
        const visit = event.detail.visit
        if (visit.method !== 'get' || !isSamePageWithOtherParameters(new URL(visit.url.toString(), window.location.origin))) {
            return
        }
        clearTimeout(timer)
        timer = setTimeout(() => { loading.value = true }, SHOW_AFTER_MS)
    })

    const offFinish = router.on('finish', () => {
        clearTimeout(timer)
        loading.value = false
    })

    onBeforeUnmount(() => {
        clearTimeout(timer)
        offStart()
        offFinish()
    })

    return loading
}
