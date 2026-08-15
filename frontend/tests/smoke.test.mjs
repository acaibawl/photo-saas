import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync } from 'node:fs'

test('frontend entry pages exist', () => {
  assert.equal(existsSync('app/pages/index.vue'), true)
  assert.equal(existsSync('app/pages/health.vue'), true)
})
