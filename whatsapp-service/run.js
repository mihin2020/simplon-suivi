'use strict'
/**
 * Lanceur auto-restart pour server.js.
 * Si le processus Node plante pour quelque raison que ce soit,
 * il redémarre automatiquement après 3 secondes.
 *
 * Usage : node run.js
 */
const { spawn } = require('child_process')
const path = require('path')

let restarts = 0

function start() {
    restarts++
    if (restarts > 1) {
        console.log(`[runner] ♻️  Redémarrage #${restarts - 1} dans 3 secondes...`)
    }

    const child = spawn(process.execPath, [path.join(__dirname, 'server.js')], {
        stdio:  'inherit',
        cwd:    __dirname,
        env:    process.env,
    })

    child.on('exit', (code, signal) => {
        if (signal === 'SIGINT' || signal === 'SIGTERM') {
            console.log('[runner] Arrêt demandé — fin.')
            process.exit(0)
        }
        console.log(`[runner] ⚠️  server.js a quitté (code ${code ?? signal}) — redémarrage dans 3s...`)
        setTimeout(start, 3000)
    })

    child.on('error', (err) => {
        console.error('[runner] Erreur lancement :', err.message)
        setTimeout(start, 3000)
    })
}

process.on('SIGINT',  () => { console.log('\n[runner] Arrêt.'); process.exit(0) })
process.on('SIGTERM', () => { console.log('[runner] Arrêt.'); process.exit(0) })

console.log('[runner] 🚀 Service WhatsApp démarré avec auto-restart.')
start()
