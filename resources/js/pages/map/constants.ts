/** Shape mask grid cell size on the plan (10 m × 10 m). */
export const GARDEN_SHAPE_CELL_CM = 1000;

export const GARDEN_BOUNDARY_MIN_VERTICES = 4;

export const GARDEN_PADDING = 24;
export const CM_TO_PX = 0.5;
export const GARDEN_GRID_CELL_CM = 30;

/** Aiaplaani ruudustiku jooned (manuaalne/plaanivaade). */
export const GARDEN_GRID_MINOR_LINE = 'rgba(34, 98, 58, 0.12)';
export const GARDEN_GRID_MAJOR_LINE = 'rgba(34, 98, 58, 0.24)';
/** Servajoone klass (overlay peal ruudustiku — gradient ei joonista paremat/alumist serva). */
export const GARDEN_GRID_EDGE_CLASS =
    'pointer-events-none absolute inset-0 z-30 box-border border-2 border-[rgba(22,101,52,0.48)]';

/** Ühe peenraruudu vaikimisi mõõt (cm) uue peenra loomisel. */
export const DEFAULT_BED_CELL_SIZE_CM = 30;

/** Uus peenar algab ühe ruuduga; kuju täpsustatakse redigeerimises. */
export const DEFAULT_NEW_BED_LAYOUT: number[][] = [[1]];

export const MIN_ZOOM = 0.1;
/** "Mahuta sisu" / "Kogu aed" may go lower so large plans actually fit on screen. */
export const FIT_VIEW_MIN_ZOOM = 0.001;
export const MAX_ZOOM = 4.5;
export const FOCUSED_BED_MIN_ZOOM = 0.75;

/** Ortofoto: 100% = kogu aed vaates; väiksem % = laiem kontekst ümber aia. */
export const ORTOPHOTO_MIN_ZOOM_FACTOR = 0.25;
export const ORTOPHOTO_MAX_ZOOM_FACTOR = 4;

/** Vahe aia ja plaanivaate serva vahel „mahuta vaatesse“ juures. */
export const PLANNER_FIT_VIEW_PADDING_PX = 24;

export const MIN_PINCH_DISTANCE_PX = 10;
export const MIN_BED_VISUAL_SIZE = 44;
export const MAX_GARDEN_SURFACE_WIDTH = 3200;
export const MAX_GARDEN_SURFACE_HEIGHT = 2200;

export const PAN_CLICK_SUPPRESS_PX = 8;
export const PLANNER_LANDSCAPE_HINT_KEY = 'planner-landscape-hint-dismissed';

export const DEFAULT_CREATE_GARDEN_WIDTH_M = 12;
export const DEFAULT_CREATE_GARDEN_HEIGHT_M = 8;

export const INAADRESS_GAZETTEER_URL =
    'https://inaadress.maaamet.ee/inaadress/gazetteer';
