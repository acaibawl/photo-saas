import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync } from 'node:fs'

test('frontend entry pages exist', () => {
  assert.equal(existsSync('app/pages/index.vue'), true)
  assert.equal(existsSync('app/pages/health.vue'), true)
  assert.equal(existsSync('app/pages/staff/login.vue'), true)
  assert.equal(existsSync('app/pages/guardian/login.vue'), true)
  assert.equal(existsSync('app/pages/staff/index.vue'), true)
  assert.equal(existsSync('app/pages/guardian/index.vue'), true)
  assert.equal(existsSync('app/plugins/api-client.ts'), true)
  assert.equal(existsSync('app/stores/auth.ts'), true)
})
