import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'
import tseslint from 'typescript-eslint'

export default [
    { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    {
        files: ['**/*.vue'],
        languageOptions: { parserOptions: { parser: tseslint.parser } },
    },
    {
        languageOptions: { globals: { ...globals.browser, route: 'readonly' } },
        rules: {
            // CLAUDE.md §10: no console output in production.
            'no-console': 'error',
            // Inertia pages are named Index, Show, Create, Edit.
            'vue/multi-word-component-names': 'off',
            // Paginator labels ("&laquo; Previous") are server-generated HTML entities rendered on <Link>.
            'vue/no-v-text-v-html-on-component': 'off',
            // TypeScript handles these itself (declaration merging, DOM types); the base rules give false positives.
            'no-undef': 'off',
            'no-redeclare': 'off',
            '@typescript-eslint/no-unused-expressions': ['error', { allowTernary: true, allowShortCircuit: true }],
            // Existing debt, to be paid down page by page: reported but not blocking.
            'vue/no-mutating-props': 'warn',
            '@typescript-eslint/no-explicit-any': 'warn',
            '@typescript-eslint/no-unused-vars': 'warn',
            'vue/no-unused-vars': 'warn',
        },
    },
]
