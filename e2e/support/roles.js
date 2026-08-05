// @ts-check
const path = require('node:path')
const { test } = require('@playwright/test')

/**
 * Runs the enclosing describe block as a signed-in role.
 *
 * The session cookies are minted once in global-setup.js, so specs pay no
 * login cost and cannot fail for auth reasons unrelated to what they assert.
 * Omit this to run as a guest.
 *
 * @param {'admin' | 'member'} role
 */
function asRole (role) {
  test.use({
    storageState: path.join(__dirname, '..', 'playwright', '.auth', `${role}.json`),
  })
}

module.exports = { asRole }
