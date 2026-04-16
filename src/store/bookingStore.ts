import { create } from "zustand";
import type {
  BookingStep,
  CustomerInfo,
  Extra,
  Package,
  PriceBreakdownItem,
  SelectedExtra,
  Space,
} from "@/types";

interface BookingState {
  // ── Navigation ───────────────────────────────────────────────────────────
  currentStep: BookingStep;

  // ── Step 1: Selection ────────────────────────────────────────────────────
  selectedSpace: Space | null;
  selectedPackage: Package | null;

  // ── Step 2: Scheduling ───────────────────────────────────────────────────
  selectedDate: string; // YYYY-MM-DD
  selectedStartTime: string; // HH:MM
  selectedEndTime: string; // HH:MM

  // ── Step 3: Add-ons ──────────────────────────────────────────────────────
  availableExtras: Extra[];
  selectedExtras: SelectedExtra[];

  // ── Step 4: Customer info ────────────────────────────────────────────────
  customerInfo: CustomerInfo;

  // ── Step 5: Checkout ─────────────────────────────────────────────────────
  checkoutUrl: string | null;
  bookingId: number | null;
  totalPrice: number;
  priceBreakdown: PriceBreakdownItem[];

  // ── Step 6: Confirmation ─────────────────────────────────────────────────
  isConfirmed: boolean;

  // ── Actions ──────────────────────────────────────────────────────────────
  setStep: (step: BookingStep) => void;
  nextStep: () => void;
  prevStep: () => void;
  setSpace: (space: Space | null) => void;
  setPackage: (pkg: Package | null) => void;
  setDate: (date: string) => void;
  setStartTime: (time: string) => void;
  setEndTime: (time: string) => void;
  setAvailableExtras: (extras: Extra[]) => void;
  toggleExtra: (extra_id: number, quantity?: number) => void;
  setCustomerInfo: (info: Partial<CustomerInfo>) => void;
  setCheckoutData: (data: {
    checkoutUrl: string;
    bookingId: number;
    totalPrice: number;
    breakdown: PriceBreakdownItem[];
  }) => void;
  setPriceBreakdown: (breakdown: PriceBreakdownItem[], total: number) => void;
  confirmBooking: () => void;
  reset: () => void;
}

const DEFAULT_CUSTOMER: CustomerInfo = {
  name: "",
  email: "",
  phone: "",
  notes: "",
};

export const useBookingStore = create<BookingState>((set, get) => ({
  // ── Initial state ────────────────────────────────────────────────────────
  currentStep: 1,
  selectedSpace: null,
  selectedPackage: null,
  selectedDate: "",
  selectedStartTime: "",
  selectedEndTime: "",
  availableExtras: [],
  selectedExtras: [],
  customerInfo: { ...DEFAULT_CUSTOMER },
  checkoutUrl: null,
  bookingId: null,
  totalPrice: 0,
  priceBreakdown: [],
  isConfirmed: false,

  // ── Navigation ───────────────────────────────────────────────────────────
  setStep: (step) => set({ currentStep: step }),
  nextStep: () =>
    set((s) => ({
      currentStep: Math.min(s.currentStep + 1, 6) as BookingStep,
    })),
  prevStep: () =>
    set((s) => ({
      currentStep: Math.max(s.currentStep - 1, 1) as BookingStep,
    })),

  // ── Step 1 ───────────────────────────────────────────────────────────────
  setSpace: (space) => set({ selectedSpace: space, selectedPackage: null }),
  setPackage: (pkg) => set({ selectedPackage: pkg, selectedSpace: null }),

  // ── Step 2 ───────────────────────────────────────────────────────────────
  setDate: (date) =>
    set({
      selectedDate: date,
      selectedStartTime: "",
      selectedEndTime: "",
      selectedExtras: [],
    }),
  setStartTime: (time) =>
    set({ selectedStartTime: time, selectedEndTime: "", selectedExtras: [] }),
  setEndTime: (time) => set({ selectedEndTime: time }),

  // ── Step 3 ───────────────────────────────────────────────────────────────
  setAvailableExtras: (extras) => set({ availableExtras: extras }),

  toggleExtra: (extra_id, quantity = 1) => {
    const current = get().selectedExtras;
    const exists = current.find((e) => e.extra_id === extra_id);

    if (exists) {
      // Remove
      set({ selectedExtras: current.filter((e) => e.extra_id !== extra_id) });
    } else {
      // Add
      set({ selectedExtras: [...current, { extra_id, quantity }] });
    }
  },

  // ── Step 4 ───────────────────────────────────────────────────────────────
  setCustomerInfo: (info) =>
    set((s) => ({ customerInfo: { ...s.customerInfo, ...info } })),

  // ── Step 5 ───────────────────────────────────────────────────────────────
  setCheckoutData: ({ checkoutUrl, bookingId, totalPrice, breakdown }) =>
    set({ checkoutUrl, bookingId, totalPrice, priceBreakdown: breakdown }),

  setPriceBreakdown: (breakdown, total) =>
    set({ priceBreakdown: breakdown, totalPrice: total }),

  // ── Step 6 ───────────────────────────────────────────────────────────────
  confirmBooking: () => set({ isConfirmed: true }),

  // ── Reset ────────────────────────────────────────────────────────────────
  reset: () =>
    set({
      currentStep: 1,
      selectedSpace: null,
      selectedPackage: null,
      selectedDate: "",
      selectedStartTime: "",
      selectedEndTime: "",
      availableExtras: [],
      selectedExtras: [],
      customerInfo: { ...DEFAULT_CUSTOMER },
      checkoutUrl: null,
      bookingId: null,
      totalPrice: 0,
      priceBreakdown: [],
      isConfirmed: false,
    }),
}));
