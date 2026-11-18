// Splits plain-text instructions (one step per line) and pulls out the importer's trailing "Źródło: …" line.
export function parseInstructions(text) {
  const steps = String(text ?? '')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line !== '')

  let source = null
  const last = steps[steps.length - 1]
  if (last !== undefined && last.startsWith('Źródło:')) {
    steps.pop()
    source = last.slice('Źródło:'.length).trim()
  }

  return { steps, source }
}
