import { suggestive } from 'yup-locale-ja'
import { setLocale, type LocaleObject } from 'yup'

const japaneseLocale = {
	...suggestive,
	mixed: {
		...suggestive.mixed,
		required: ({ label }: { label?: string }) => `${label ? `${label}は` : ''}必ず入力してください。`,
	},
} satisfies LocaleObject

setLocale(japaneseLocale)

export default defineNuxtPlugin(() => {})
