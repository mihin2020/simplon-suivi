export interface PhoneCountry {
    iso: string
    name: string
    dial: string
}

/** Common dial codes for Simplon BF context (West Africa + frequent diaspora). */
export const PHONE_COUNTRIES: PhoneCountry[] = [
    { iso: 'BF', name: 'Burkina Faso', dial: '226' },
    { iso: 'CI', name: "Côte d'Ivoire", dial: '225' },
    { iso: 'ML', name: 'Mali', dial: '223' },
    { iso: 'NE', name: 'Niger', dial: '227' },
    { iso: 'TG', name: 'Togo', dial: '228' },
    { iso: 'BJ', name: 'Bénin', dial: '229' },
    { iso: 'GH', name: 'Ghana', dial: '233' },
    { iso: 'SN', name: 'Sénégal', dial: '221' },
    { iso: 'GN', name: 'Guinée', dial: '224' },
    { iso: 'GW', name: 'Guinée-Bissau', dial: '245' },
    { iso: 'GM', name: 'Gambie', dial: '220' },
    { iso: 'LR', name: 'Liberia', dial: '231' },
    { iso: 'SL', name: 'Sierra Leone', dial: '232' },
    { iso: 'MR', name: 'Mauritanie', dial: '222' },
    { iso: 'CM', name: 'Cameroun', dial: '237' },
    { iso: 'GA', name: 'Gabon', dial: '241' },
    { iso: 'CG', name: 'Congo', dial: '242' },
    { iso: 'CD', name: 'RDC', dial: '243' },
    { iso: 'TD', name: 'Tchad', dial: '235' },
    { iso: 'CF', name: 'Centrafrique', dial: '236' },
    { iso: 'NG', name: 'Nigeria', dial: '234' },
    { iso: 'MA', name: 'Maroc', dial: '212' },
    { iso: 'DZ', name: 'Algérie', dial: '213' },
    { iso: 'TN', name: 'Tunisie', dial: '216' },
    { iso: 'EG', name: 'Égypte', dial: '20' },
    { iso: 'FR', name: 'France', dial: '33' },
    { iso: 'BE', name: 'Belgique', dial: '32' },
    { iso: 'CH', name: 'Suisse', dial: '41' },
    { iso: 'CA', name: 'Canada', dial: '1' },
    { iso: 'US', name: 'États-Unis', dial: '1' },
    { iso: 'DE', name: 'Allemagne', dial: '49' },
    { iso: 'GB', name: 'Royaume-Uni', dial: '44' },
]

export const DEFAULT_PHONE_COUNTRY = PHONE_COUNTRIES[0] // Burkina Faso

/** PNG flags — Windows does not render emoji flags reliably. */
export function flagUrl(iso: string, width = 40): string {
    return `https://flagcdn.com/w${width}/${iso.toLowerCase()}.png`
}

export function digitsOnly(value: string): string {
    return value.replace(/\D+/g, '')
}

/** Longest matching dial code first (avoids matching "1" before "226"). */
export function detectCountry(fullPhone: string | null | undefined): PhoneCountry {
    const digits = digitsOnly(fullPhone ?? '')
    if (!digits) {
        return DEFAULT_PHONE_COUNTRY
    }

    const sorted = [...PHONE_COUNTRIES].sort((a, b) => b.dial.length - a.dial.length)
    for (const country of sorted) {
        if (digits.startsWith(country.dial)) {
            return country
        }
    }

    return DEFAULT_PHONE_COUNTRY
}

export function nationalNumber(fullPhone: string | null | undefined, country: PhoneCountry): string {
    const digits = digitsOnly(fullPhone ?? '')
    if (digits.startsWith(country.dial)) {
        return digits.slice(country.dial.length)
    }

    return digits
}

export function formatInternational(country: PhoneCountry, national: string): string {
    const local = digitsOnly(national)
    if (!local) {
        return ''
    }

    return `+${country.dial}${local}`
}
