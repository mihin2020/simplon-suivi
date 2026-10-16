<script setup lang="ts">
import { onMounted } from 'vue'
import { contractFontStack, loadContractFont, type ContractFontOption } from '@/composables/useContractFont'

/** Each font shown in itself, so the choice is made by eye. */
const props = defineProps<{ fonts: ContractFontOption[] }>()
const model = defineModel<string>({ required: true })

onMounted(() => props.fonts.forEach(loadContractFont))
</script>

<template>
    <div class="fonts" role="radiogroup" aria-label="Police du contrat">
        <button
            v-for="font in fonts"
            :key="font.value"
            type="button"
            role="radio"
            class="font"
            :class="{ on: model === font.value }"
            :aria-checked="model === font.value"
            @click="model = font.value"
        >
            <span class="sample" :style="{ fontFamily: contractFontStack(font) }">Aa</span>
            <span class="name">{{ font.label }}</span>
            <span class="line" :style="{ fontFamily: contractFontStack(font) }">Article 1 : Objet du contrat</span>
        </button>
    </div>
</template>

<style scoped>
.fonts { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 8px; }
.font { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 10px 12px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; text-align: left; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
.font:hover { border-color: #c3ccd6; }
.font.on { border-color: #E5004C; background: #fff5f8; }
.sample { font-size: 22px; line-height: 1.1; color: #1F3A4D; }
.name { font-size: 12px; font-weight: 700; color: #191c1e; }
.line { font-size: 11.5px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
</style>
