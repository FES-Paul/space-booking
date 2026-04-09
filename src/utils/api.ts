import type {
  AvailabilityResponse,
  BookingCreateResponse,
  Extra,
  Package,
  PricingResponse,
  SelectedExtra,
  Space,
} from '@/types';

const BASE = () => window.sbConfig.apiBase;
const NONCE = () => window.sbConfig.nonce;

async function apiFetch<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const url = `${BASE()}${path}`;
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'X-WP-Nonce': NONCE(),
    ...(options.headers as Record<string, string>),
  };

  const res = await fetch(url, { ...options, headers });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: res.statusText }));
    throw new Error((err as { message?: string }).message ?? res.statusText);
  }

  return res.json() as Promise<T>;
}

// ── Spaces & Packages ─────────────────────────────────────────────────────────

export const fetchSpaces = () => apiFetch<Space[]>('/spaces');

export const fetchSpace = (id: number) => apiFetch<Space>(`/spaces/${id}`);

export const fetchPackages = () => apiFetch<Package[]>('/packages');

// ── Availability ──────────────────────────────────────────────────────────────

export const fetchAvailability = (spaceId: number, date: string) =>
  apiFetch<AvailabilityResponse>(
    `/availability?space_id=${spaceId}&date=${date}`
  );

// ── Extras ────────────────────────────────────────────────────────────────────

export const fetchExtras = (
  spaceId: number,
  date: string,
  startTime: string,
  endTime: string
) =>
  apiFetch<Extra[]>(
    `/extras?space_id=${spaceId}&date=${date}&start_time=${startTime}&end_time=${endTime}`
  );

// ── Pricing ───────────────────────────────────────────────────────────────────

export const fetchPricing = (params: {
  space_id: number;
  date: string;
  start_time: string;
  end_time: string;
  extras?: SelectedExtra[];
  package_id?: number;
}) => {
  const qs = new URLSearchParams();
  qs.set('space_id', String(params.space_id));
  qs.set('date', params.date);
  qs.set('start_time', params.start_time);
  qs.set('end_time', params.end_time);
  if (params.package_id) qs.set('package_id', String(params.package_id));
  (params.extras ?? []).forEach((e, i) => {
    qs.set(`extras[${i}][extra_id]`, String(e.extra_id));
    qs.set(`extras[${i}][quantity]`, String(e.quantity));
  });
  return apiFetch<PricingResponse>(`/pricing?${qs.toString()}`);
};

// ── Create Booking ────────────────────────────────────────────────────────────

export const createBooking = (payload: {
  space_id: number;
  package_id?: number;
  date: string;
  start_time: string;
  end_time: string;
  customer_name: string;
  customer_email: string;
  customer_phone?: string;
  notes?: string;
  extras?: SelectedExtra[];
}) =>
  apiFetch<BookingCreateResponse>('/bookings', {
    method: 'POST',
    body: JSON.stringify(payload),
  });

// ── Customer Lookup ───────────────────────────────────────────────────────────

export const sendMagicLink = (email: string) =>
  apiFetch<{ message: string }>('/customer/lookup', {
    method: 'POST',
    body: JSON.stringify({ email }),
  });

export const fetchCustomerBookings = (token: string) =>
  apiFetch<{ email: string; bookings: unknown[] }>(`/customer/bookings?token=${token}`);
