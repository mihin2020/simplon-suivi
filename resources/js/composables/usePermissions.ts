import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export interface AuthUser {
    id: string
    full_name: string
    email: string
    role: string
    role_label: string
    is_super_admin: boolean
    permissions: string[] | null
}

export function usePermissions() {
    const page = usePage()

    const authUser = computed(() => page.props.auth?.user as AuthUser | null | undefined)
    const isSuperAdmin = computed(() => authUser.value?.is_super_admin === true)
    const userRole = computed(() => authUser.value?.role)
    const permissionSlugs = computed(() => authUser.value?.permissions ?? null)

    function can(slug: string): boolean {
        if (!authUser.value) return false
        if (isSuperAdmin.value) return true
        if (permissionSlugs.value === null) return true
        return permissionSlugs.value.includes(slug)
    }

    function canAny(slugs: string[]): boolean {
        if (slugs.length === 0) return true
        return slugs.some(can)
    }

    function canAll(slugs: string[]): boolean {
        if (slugs.length === 0) return true
        return slugs.every(can)
    }

    return {
        authUser,
        isSuperAdmin,
        userRole,
        can,
        canAny,
        canAll,
    }
}
