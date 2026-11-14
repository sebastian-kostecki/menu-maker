/**
 * Client-side mirror of FamilyMemberRequest's weight/height rules: both are required from the 19th birthday on,
 * weight must be 30–300 kg and height 100–250 cm.
 */
export interface FamilyMemberBodyFields {
  birth_date?: string | null
  weight_kg?: string | number | null
  height_cm?: string | number | null
}

export type FamilyMemberBodyErrors = Partial<Record<'weight_kg' | 'height_cm', string>>

const ADULT_AGE = 19

function isAdult(birthDate: string | null | undefined): boolean {
  const birth = birthDate ? new Date(birthDate) : null
  if (!birth || Number.isNaN(birth.getTime())) {
    return false
  }

  const now = new Date()
  let age = now.getFullYear() - birth.getFullYear()
  if (now.getMonth() < birth.getMonth() || (now.getMonth() === birth.getMonth() && now.getDate() < birth.getDate())) {
    age--
  }

  return age >= ADULT_AGE
}

function rangeError(
  value: string | number | null | undefined,
  adult: boolean,
  min: number,
  max: number,
  requiredMessage: string,
  rangeMessage: string,
): string | undefined {
  const text = String(value ?? '').trim()
  if (text === '') {
    return adult ? requiredMessage : undefined
  }

  const number = Number(text)

  return Number.isNaN(number) || number < min || number > max ? rangeMessage : undefined
}

export function useFamilyMemberBodyValidation() {
  const validateBodyData = (fields: FamilyMemberBodyFields): FamilyMemberBodyErrors => {
    const adult = isAdult(fields.birth_date)
    const errors: FamilyMemberBodyErrors = {}

    const weight = rangeError(fields.weight_kg, adult, 30, 300, 'Podaj wagę (wymagana od 19 lat).', 'Waga musi wynosić od 30 do 300 kg.')
    if (weight) {
      errors.weight_kg = weight
    }

    const height = rangeError(fields.height_cm, adult, 100, 250, 'Podaj wzrost (wymagany od 19 lat).', 'Wzrost musi wynosić od 100 do 250 cm.')
    if (height) {
      errors.height_cm = height
    }

    return errors
  }

  return { validateBodyData }
}
