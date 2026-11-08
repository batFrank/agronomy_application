export function addPadStart(number, size = 2, fillString = '0') {
  return String(number).padStart(size, fillString)
}
