// ── Domain types ──────────────────────────────────────────────────────────────

export interface Space {
  id: number;
  title: string;
  description: string;
  excerpt: string;
  thumbnail: string | null;
  hourly_rate: number;
  min_duration: number;
  max_duration: number;
  capacity: number;
  day_overrides: Record<number, DayOverride>;
  price_overrides: Array<{
    days: number[];
    start_time: string;
    end_time: string;
    hourly_rate: number;
  }> | null;
  gallery: string[];
}

export interface DayOverride {
  open?: string;
  close?: string;
  closed?: boolean;
}

export interface Package {
  id: number;
  title: string;
  description: string;
  thumbnail: string | null;
  price: number;
  duration: number;
  space_id: number;
  space_name: string | null;
  extra_ids: number[];
}

export interface Extra {
  id: number;
  title: string;
  description: string;
  price: number;
  inventory: number;
  booked_qty: number;
  available_qty: number;
  is_available: boolean;
  unavailable_reason: "sold_out" | "space_override" | null;
  thumbnail: string | null;
}

export interface TimeSlot {
  start: string; // \"H:i\"
  end: string;
  available: boolean;
}

export interface AvailabilityResponse {
  date: string;
  space_id: number;
  open_time: string | null;
  close_time: string | null;
  slots: TimeSlot[];
}

export interface PriceBreakdownItem {
  label: string;
  amount: number;
  context?: {
    type: "space" | "package" | "extra" | "segment" | "modifier";
    name?: string;
    id?: number;
  };
}

export interface PricingResponse {
  base_price: number;
  modifier_price: number;
  extras_price: number;
  total_price: number;
  duration_hours: number;
  breakdown: PriceBreakdownItem[];
}

export interface BookingCreateResponse {
  booking_id: number;
  checkout_url: string;
  total_price: number;
  breakdown: PriceBreakdownItem[];
}

export interface CustomerBooking {
  id: number;
  space_id: number;
  space_name: string;
  thumbnail: string | null;
  booking_date: string;
  start_time: string;
  end_time: string;
  duration_hours: number;
  total_price: number;
  status: "pending" | "confirmed" | "cancelled" | "refunded";
  customer_name: string;
  customer_email: string;
  extras: Array<{ extra_name: string; quantity: number; unit_price: number }>;
}

// ── Global WP config injected via wp_localize_script ─────────────────────────

declare global {
  interface Window {
    sbConfig: {
      apiBase: string;
      nonce: string;
      stripeKey: string;
      currency: string;
      symbol: string;
      dateFormat: string;
      bookingPolicy: string;
    };
  }
}

// ── Selected extras in booking wizard ────────────────────────────────────────

export interface SelectedExtra {
  extra_id: number;
  quantity: number;
}

// ── Booking wizard step state ─────────────────────────────────────────────────

export type BookingStep = 1 | 2 | 3 | 4 | 5 | 6 | 7;

export interface CustomField {
  key: string;
  label: string;
  type: "text" | "email" | "tel" | "textarea" | "checkbox" | "radio" | "select";
  required?: boolean;
  placeholder?: string;
  default?: string;
  options?: string[];
}

export type CustomerValue = string | boolean | string[];

export interface CustomerInfo {
  [key: string]: CustomerValue;
}
