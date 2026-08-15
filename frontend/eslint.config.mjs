// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt(
  {
    // Generated outputs are lint noise for app development.
    ignores: ['.nuxt/**', '.output/**', 'dist/**', 'coverage/**', 'node_modules/**'],
  },
  {
    rules: {
      // Keep developer flow smooth: warn for risky patterns, avoid hard errors.
      'no-debugger': 'warn',
      'no-alert': 'warn',
      eqeqeq: ['warn', 'smart'],
      curly: ['warn', 'multi-line'],

      // TypeScript projects should use TS-aware unused-vars checks.
      'no-unused-vars': 'off',
      '@typescript-eslint/no-unused-vars': [
        'warn',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^_',
        },
      ],

      // Useful for Nuxt/Vue + TS without forcing over-strict style.
      '@typescript-eslint/consistent-type-imports': [
        'warn',
        { prefer: 'type-imports', fixStyle: 'inline-type-imports' },
      ],
      // Keep template attribute order predictable across the team.
      'vue/attributes-order': [
        'warn',
        {
          order: [
            'DEFINITION',
            'LIST_RENDERING',
            'CONDITIONALS',
            'RENDER_MODIFIERS',
            'GLOBAL',
            ['UNIQUE', 'SLOT'],
            'TWO_WAY_BINDING',
            'OTHER_DIRECTIVES',
            'OTHER_ATTR',
            'EVENTS',
            'CONTENT',
          ],
          alphabetical: false,
        },
      ],
      'vue/multi-word-component-names': 'off',
      'vue/require-default-prop': 'off',
    },
  }
)
