import { spawn } from 'child_process'

export const printLoader = () => {
  const shapes = ['\\', '|', '/', '-']
  let index = 0
  return setInterval(() => {
    process.stdout.write(`\r${shapes[index]}`)
    index += 1
    index &= 3
  }, 250)
}

export const runNodeCommand = (command, { silentMode, loadingMode } = { silentMode: true, loadingMode: false }) => {
  const interval = loadingMode ? printLoader() : null

  function handlingExit(resolve, reject, code) {
    if (interval) clearInterval(interval)

    if (code === 0) return resolve()
    return reject(new Error(`El comando falló con el código de salida ${code}`))
  }

  return new Promise((resolve, reject) => {
    const task = spawn(command, { shell: true })

    if (!silentMode) {
      task.stdout.on('data', (data) => process.stdout.write(data.toString()))
    }

    task.stderr.on('data', (data) => process.stdout.write(data.toString()))
    task.on('close', (code) => handlingExit(resolve, reject, code))
    task.on('exit', (code) => handlingExit(resolve, reject, code))
  })
}
