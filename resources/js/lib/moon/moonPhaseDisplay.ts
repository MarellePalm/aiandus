import type { MoonPhase8 } from './moon';

/**
 * Kasutajasõbralikud nimed (sarnaselt levinud kuukalendrite UI-le), 8 faasi säilib `MoonPhase8`-is.
 */
export const MOON_PHASE_DISPLAY_ET: Record<MoonPhase8, string> = {
    Uuskuu: 'Kuu loomine',
    'Kasvav sirp': 'Noorkuu',
    'Esimene veerand': 'Poolkuu (esimene veerand)',
    'Kasvav kumer kuu': 'Kasvav kuu',
    Täiskuu: 'Täiskuu',
    'Kahanev kumer kuu': 'Kahanev kuu',
    'Viimane veerand': 'Poolkuu (viimane veerand)',
    'Kahanev sirp': 'Vanakuu',
};

export function moonPhaseDisplayLabel(phase: MoonPhase8): string {
    return MOON_PHASE_DISPLAY_ET[phase] ?? phase;
}
