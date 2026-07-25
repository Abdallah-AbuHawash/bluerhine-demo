export type Rect = { x: number; y: number; w: number; h: number };

export type Placement = Rect & {
    label: string;
    rotated: boolean;
    instance_index: number;
};

export type CutSegment = {
    kind: 'trim' | 'shelf' | 'rip' | 'size';
    axis: 'horizontal' | 'vertical';
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    length_mm: number;
};

export type SheetLayout = {
    index: number;
    sheet: { width_mm: number; height_mm: number };
    usable: Rect;
    placements: Placement[];
    offcuts: Rect[];
    cuts: CutSegment[];
    piece_counts: Record<string, number>;
    trim_cut_length_mm: number;
    piece_cut_length_mm: number;
    yield_pct: number;
};

export type EngineResult = {
    mode: 'fixed' | 'optimized';
    strategy: string;
    sheet: { width_mm: number; height_mm: number };
    config: {
        kerf_mm: number;
        trim_top_mm: number;
        trim_right_mm: number;
        trim_bottom_mm: number;
        trim_left_mm: number;
        rotation_allowed: boolean;
        include_trim_in_cut_length: boolean;
        min_offcut_mm: number;
    };
    sheets_consumed: number;
    pieces_placed: number;
    trim_cut_length_mm: number;
    piece_cut_length_mm: number;
    total_cut_length_mm: number;
    total_cut_metres: number;
    per_sheet_pieces: { sheet_index: number; pieces: Record<string, number>; total: number }[];
    offcuts: (Rect & { sheet_index: number })[];
    total_offcut_area_mm2: number;
    unplaceable_pieces: { label: string; width_mm: number; height_mm: number }[];
    layouts: SheetLayout[];
};

export type Pricing = {
    sheets: number;
    sheet_price_aed: number;
    material_total_aed: number;
    cut_metres: number;
    pieces_placed: number;
    rate: number;
    rate_unit: 'per_cut_metre' | 'per_piece' | 'per_sheet';
    cutting_basis: string;
    cutting_total_aed: number;
    subtotal_aed: number;
    vat_pct: number;
    vat_aed: number;
    total_aed: number;
};

export type LineSnapshot = {
    mode: 'fixed' | 'optimized';
    engine: EngineResult;
    material: {
        id: number;
        sku: string;
        name: string;
        brand: string | null;
        material_group: string;
        thickness_mm: number;
        sheet_w_mm: number;
        sheet_h_mm: number;
        color_name: string | null;
        selling_price_aed: number;
        rotation_allowed: boolean;
    };
    cutting_rate: { material_group: string; thickness_mm: number; rate: number; rate_unit: string; label: string };
    parameters: {
        kerf_mm: number;
        trim_mm: number;
        vat_pct: number;
        quote_validity_days: number;
        include_trim_in_cut_length: boolean;
    };
    pricing: Pricing;
    cut_metres: number;
    sheets_consumed: number;
    rows: { label: string; width_mm: number; height_mm: number; qty: number }[];
    promised_date: string;
    valid_until: string;
    frozen_at: string | null;
};

export type QuoteLine = {
    id: number;
    material_id: number;
    mode: 'fixed' | 'optimized';
    sheets_consumed: number;
    cut_metres: number;
    snapshot: LineSnapshot;
    cut_jobs: { cut_metres: number; scheduled_date: string }[];
};

export type Quote = {
    id: number;
    reference: string;
    customer_name: string;
    customer_reference: string | null;
    status: 'draft' | 'issued' | 'ordered';
    currency: string;
    material_total_aed: number;
    cutting_total_aed: number;
    subtotal_aed: number;
    vat_pct: number;
    vat_aed: number;
    total_aed: number;
    promised_date: string | null;
    valid_until: string | null;
    issued_at: string | null;
};

export type MaterialOption = {
    id: number;
    sku: string;
    name: string;
    brand: string | null;
    material_group: string;
    thickness_mm: number;
    sheet_w_mm: number;
    sheet_h_mm: number;
    color_name: string | null;
    selling_price_aed: number;
    rotation_allowed: boolean;
    stock_qty: number;
    available_sheets: number;
};

export type ParsedRow = {
    material_hint: string | null;
    thickness_mm: number | null;
    width_mm: number | null;
    height_mm: number | null;
    qty: number;
    notes: string | null;
    material_id?: number | null;
    suggested_material_id?: number | null;
};

export type ParseResult = {
    pieces: ParsedRow[];
    confidence: number;
    warnings: string[];
    source: 'api' | 'offline_fixture';
};
