import { execSync } from 'child_process'
import fs from 'fs'
import path from 'path'

function getCurrentGitBranch() {
  try {
    return execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim()
  } catch (error) {
    console.error('❌ Error fetching Git branch:', error.message)
    return null
  }
}

function getCurrentCommitHash() {
  try {
    return execSync('git rev-parse HEAD', { encoding: 'utf8' }).trim()
  } catch (error) {
    console.error('❌ Error fetching Git commit:', error.message)
    return null
  }
}

function formatDate() {
  const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ]

  const now = new Date()
  const month = months[now.getMonth()]
  const day = now.getDate()
  const year = now.getFullYear()
  const hours = String(now.getHours()).padStart(2, '0')
  const minutes = String(now.getMinutes()).padStart(2, '0')
  const seconds = String(now.getSeconds()).padStart(2, '0')

  return `${month} - ${day} - ${year} | [${hours}:${minutes}:${seconds}]`
}

function createVersionFile(outputDir, branch, commit) {
  const buildDir = path.resolve(outputDir)

  fs.mkdirSync(buildDir, { recursive: true })

  const versionFilePath = path.join(buildDir, 'version.json')
  const formattedDate = formatDate()

  const versionData = {
    build: {
      date: formattedDate,
      branch: branch || 'unknown',
      commit: commit || 'unknown',
    },
  }

  try {
    fs.writeFileSync(versionFilePath, JSON.stringify(versionData, null, 2))
    console.log(`✅ version.json file created/updated successfully at ${versionFilePath}.`)
  } catch (error) {
    console.error('❌ Error writing version.json file:', error.message)
  }
}

export function generateVersionFile(outputDir) {
  const branch = getCurrentGitBranch()
  const commit = getCurrentCommitHash()
  createVersionFile(outputDir, branch, commit)
}
