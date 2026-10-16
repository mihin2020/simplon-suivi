<script setup lang="ts">
/**
 * Choice of the header/footer pair, each shown as a miniature page, plus
 * where it applies (every page or the first one).
 */
export interface LetterheadOption {
    id: string
    name: string
    header_url: string | null
    footer_url: string | null
    header_height_mm: number | null
    footer_height_mm: number | null
}

defineProps<{ letterheads: LetterheadOption[]; error?: string }>()
const id = defineModel<string>({ required: true })
const scope = defineModel<string>('scope', { default: 'all_pages' })
</script>

<template>
    <div class="lh">
        <div class="cards" role="radiogroup" aria-label="En-tête et pied de page">
            <button type="button" role="radio" class="card" :class="{ on: !id }" :aria-checked="!id" @click="id = ''">
                <span class="mini empty"><span class="material-symbols-outlined">block</span></span>
                <span class="name">Aucun</span>
            </button>
            <button
                v-for="l in letterheads"
                :key="l.id"
                type="button"
                role="radio"
                class="card"
                :class="{ on: id === l.id }"
                :aria-checked="id === l.id"
                @click="id = l.id"
            >
                <span class="mini">
                    <img v-if="l.header_url" :src="l.header_url" alt="" />
                    <span class="lines"><i /><i /><i /></span>
                    <img v-if="l.footer_url" :src="l.footer_url" alt="" class="foot" />
                </span>
                <span class="name">{{ l.name }}</span>
            </button>
        </div>
        <p v-if="error" class="error-msg">{{ error }}</p>

        <div v-if="id" class="scope">
            <label class="radio"><input v-model="scope" type="radio" value="all_pages" /> Sur toutes les pages</label>
            <label class="radio"><input v-model="scope" type="radio" value="first_page" /> Sur la première page uniquement</label>
        </div>
    </div>
</template>

<style scoped>
.lh { display: flex; flex-direction: column; gap: 10px; }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
.card { display: flex; flex-direction: column; gap: 6px; padding: 8px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; cursor: pointer; text-align: left; }
.card:hover { border-color: #c3ccd6; }
.card.on { border-color: #E5004C; background: #fff5f8; }
.mini { position: relative; display: flex; flex-direction: column; aspect-ratio: 210 / 297; background: #fff; border: 1px solid #e8edf2; border-radius: 4px; overflow: hidden; }
.mini img { display: block; width: 100%; }
.mini .foot { margin-top: auto; }
.mini.empty { align-items: center; justify-content: center; background: #f6f8fa; color: #c3ccd6; }
.lines { display: flex; flex-direction: column; gap: 4px; padding: 8px 12%; }
.lines i { display: block; height: 3px; border-radius: 2px; background: #e8edf2; }
.lines i:last-child { width: 60%; }
.name { font-size: 12.5px; font-weight: 600; color: #191c1e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.scope { display: flex; gap: 20px; flex-wrap: wrap; }
.radio { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #374151; cursor: pointer; }
.radio input { width: 17px; height: 17px; accent-color: #E5004C; }
.error-msg { font-size: 12px; color: #ba1a1a; }
</style>
