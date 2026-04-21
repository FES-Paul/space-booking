import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import type {
  BookingStep,
  CustomerInfo,
  CustomField,
  CustomerValue,
  Extra,
  Package,
  PriceBreakdownItem,
  SelectedExtra,
  Space,
} from "../types";

import { checkCartHasBooking } from "../utils/api";

interface BookingState {
  bookingPolicy: string;
  currentStep: BookingStep;
  selectedSpace: Space | null;
  selectedPackage: Package | null;
  selectedDate: string;
  selectedStartTime: string;
  selectedEndTime: string;
  availableExtras: Extra[];
  selectedExtras: SelectedExtra[];
  customerInfo: CustomerInfo;
  customerFields: CustomField[];
  checkoutUrl: string | null;
  bookingId: number | null;
  bookingStatus: "pending" | "confirmed" | "error";
  totalPrice: number;
  priceBreakdown: PriceBreakdownItem[];
  isConfirmed: boolean;
  hasCartBooking: boolean;
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
  setCustomerField: (key: string, value: CustomerValue) => void;
  setCustomerFields: (fields: CustomField[]) => void;
  fetchCustomerFields: () => Promise<void>;
  validateCustomerInfo: () => boolean;
  setCheckoutData: (data: {
    checkoutUrl: string;
    bookingId: number;
    totalPrice: number;
    breakdown: PriceBreakdownItem[];
  }) => void;
  setPriceBreakdown: (breakdown: PriceBreakdownItem[], total: number) => void;
  confirmBooking: () => void;
  checkCartBooking: () => Promise<void>;
  loadBookingStatus: (id: number) => Promise<void>;
  setBookingStatus: (status: "pending" | "confirmed" | "error") => void;
  clearPersistedState: () => void;
  setHasCartBooking: (has: boolean) => void;
  reset: () => void;
  setBookingPolicy: (policy: string) => void;
}

const DEFAULT_CUSTOMER: CustomerInfo = {};

export const useBookingStore = create<BookingState>()(
  persist(
    (set, get) => ({
      // ── Initial state ────────────────────────────────────────────────────────
      currentStep: 1,
      bookingPolicy: "",
      selectedSpace: null,
      selectedPackage: null,
      selectedDate: "",
      selectedStartTime: "",
      selectedEndTime: "",
      availableExtras: [],
      selectedExtras: [],
      customerInfo: { ...DEFAULT_CUSTOMER },
      customerFields: [],
      checkoutUrl: null,
      bookingId: null,
      bookingStatus: "pending",
      totalPrice: 0,
      priceBreakdown: [],
      isConfirmed: false,
      hasCartBooking: false,

      // ── Navigation ───────────────────────────────────────────────────────────
      setStep: (step: BookingStep) => set({ currentStep: step }),
      nextStep: () =>
        set((state) => ({
          currentStep: Math.min(state.currentStep + 1, 7) as BookingStep,
        })),
      prevStep: () =>
        set((state) => ({
          currentStep: Math.max(state.currentStep - 1, 1) as BookingStep,
        })),

      // ── Step 1 ───────────────────────────────────────────────────────────────
      setSpace: (space: Space | null) =>
        set({ selectedSpace: space, selectedPackage: null }),
      setPackage: (pkg: Package | null) =>
        set({ selectedPackage: pkg, selectedSpace: null }),

      // ── Step 2 ───────────────────────────────────────────────────────────────
      setDate: (date: string) =>
        set({
          selectedDate: date,
          selectedStartTime: "",
          selectedEndTime: "",
          selectedExtras: [],
        }),
      setStartTime: (time: string) =>
        set({
          selectedStartTime: time,
          selectedEndTime: "",
          selectedExtras: [],
        }),
      setEndTime: (time: string) => set({ selectedEndTime: time }),

      // ── Step 3 ───────────────────────────────────────────────────────────────
      setAvailableExtras: (extras: Extra[]) => set({ availableExtras: extras }),

      toggleExtra: (extra_id: number, quantity: number = 1) => {
        const current = get().selectedExtras;
        const exists = current.find((e) => e.extra_id === extra_id);

        if (exists) {
          // Remove
          set({
            selectedExtras: current.filter((e) => e.extra_id !== extra_id),
          });
        } else {
          // Add
          set({ selectedExtras: [...current, { extra_id, quantity }] });
        }
      },

      // ── Step 4 ───────────────────────────────────────────────────────────────
      setCustomerField: (key: string, value: CustomerValue) =>
        set((state) => ({
          customerInfo: { ...state.customerInfo, [key]: value },
        })),
      setCustomerFields: (fields: CustomField[]) =>
        set({ customerFields: fields }),
      fetchCustomerFields: async () => {
        try {
          const res = await fetch(
            `${window.sbConfig.apiBase}/customer/fields/`,
            {
              headers: { "X-WP-Nonce": window.sbConfig.nonce },
            },
          );
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          const data = await res.json();
          console.log("Customer fields:", data);
          if (
            data.fields &&
            Array.isArray(data.fields) &&
            data.fields.length > 0
          ) {
            set({ customerFields: data.fields });
            const state = get();
            const newCustomerInfo = { ...state.customerInfo };
            data.fields.forEach((f: CustomField) => {
              if (f.default !== undefined && f.default !== "") {
                newCustomerInfo[f.key] = f.default;
              }
            });
            set({ customerInfo: newCustomerInfo });
          } else {
            console.warn("Empty fields response, using defaults");
            const defaults: CustomField[] = [
              { key: "name", label: "Full Name", type: "text", required: true },
              {
                key: "email",
                label: "Email Address",
                type: "email",
                required: true,
              },
              { key: "phone", label: "Phone", type: "tel", required: false },
              {
                key: "notes",
                label: "Special Requests",
                type: "textarea",
                required: false,
              },
            ];
            set({ customerFields: defaults });
          }
        } catch (e) {
          console.error("Fetch customer fields failed:", e);
          const defaults: CustomField[] = [
            { key: "name", label: "Full Name", type: "text", required: true },
            {
              key: "email",
              label: "Email Address",
              type: "email",
              required: true,
            },
            { key: "phone", label: "Phone", type: "tel", required: false },
            {
              key: "notes",
              label: "Special Requests",
              type: "textarea",
              required: false,
            },
          ];
          set({ customerFields: defaults });
        }
      },
      validateCustomerInfo: (): boolean => {
        const { customerFields, customerInfo } = get();
        return customerFields.every((f) => {
          if (!f.required) return true;
          const val = customerInfo[f.key];
          if (val === "" || val === undefined || val === null) return false;
          if (
            f.type === "email" &&
            !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val as string)
          )
            return false;
          return true;
        });
      },

      // ── Step 5 ───────────────────────────────────────────────────────────────
      setCheckoutData: ({
        checkoutUrl,
        bookingId,
        totalPrice,
        breakdown,
      }: {
        checkoutUrl: string;
        bookingId: number;
        totalPrice: number;
        breakdown: PriceBreakdownItem[];
      }) =>
        set({ checkoutUrl, bookingId, totalPrice, priceBreakdown: breakdown }),

      setPriceBreakdown: (
        rawBreakdown: PriceBreakdownItem[],
        total: number,
      ) => {
        const state = get();
        const breakdown = rawBreakdown.map((item: PriceBreakdownItem) => {
          let label = item.label;
          if (label === "Package price" && state.selectedPackage) {
            label = `${state.selectedPackage.title}`;
          } else if (label === "Extras" && state.selectedExtras.length > 0) {
            const extraNames = state.selectedExtras
              .map((e: SelectedExtra) => {
                const extra = state.availableExtras.find(
                  (ex: Extra) => ex.id === e.extra_id,
                );
                return extra ? extra.title : `Extra #${e.extra_id}`;
              })
              .join(" + ");
            label = `Extras: ${extraNames}`;
          } else if (
            state.selectedSpace &&
            (label.includes("–") || label.match(/^\\d{2}:\\d{2}–\\d{2}:\\d{2}/))
          ) {
            label = `${state.selectedSpace.title}: ${label}`;
          }
          return { ...item, label };
        });
        set({ priceBreakdown: breakdown, totalPrice: total });
      },

      // ── Step 6 ───────────────────────────────────────────────────────────────
      confirmBooking: () => {
        set({ isConfirmed: true });
        get().reset();
      },

      // ── Reset ────────────────────────────────────────────────────────────────
      setBookingPolicy: (policy: string) => set({ bookingPolicy: policy }),

      // ── Cart ──────────────────────────────────────────────────────────────
      checkCartBooking: async () => {
        try {
          const res = await checkCartHasBooking();
          if (res.hasCartBooking) {
            get().reset();
          } else {
            set({ hasCartBooking: false });
          }
        } catch (e) {
          console.error("Cart check failed:", e);
          set({ hasCartBooking: false });
        }
      },

      clearPersistedState: () => localStorage.removeItem("sb-booking-state"),
      setHasCartBooking: (has: boolean) => set({ hasCartBooking: has }),

      // ── Booking Status ───────────────────────────────────────────────────────
      loadBookingStatus: async (id: number) => {
        try {
          const res = await fetch(`${window.sbConfig.apiBase}/bookings/${id}`);
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          const data = await res.json();
          const status = data.status || data.booking?.status || "error";
          set({ bookingStatus: status as "pending" | "confirmed" | "error" });
          if (data.booking) {
            // Populate store from booking data if needed
            const b = data.booking;
            set({
              selectedDate: b.booking_date || "",
              selectedStartTime: b.start_time || "",
              selectedEndTime: b.end_time || "",
              totalPrice: parseFloat(b.total_price || "0"),
              customerInfo: {
                name: b.customer_name || "",
                email: b.customer_email || "",
                phone: b.customer_phone || "",
              },
            });
          }
        } catch (e) {
          console.error("loadBookingStatus failed:", e);
          set({ bookingStatus: "error" });
        }
      },

      setBookingStatus: (status: "pending" | "confirmed" | "error") =>
        set({ bookingStatus: status }),

      reset: () => {
        get().clearPersistedState();
        set({
          currentStep: 1,
          bookingPolicy: "",
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
          bookingStatus: "pending",
          totalPrice: 0,
          priceBreakdown: [],
          isConfirmed: false,
          hasCartBooking: false,
        });
      },
    }),
    {
      name: "sb-booking-state",
      storage: createJSONStorage(() => localStorage),
    },
  ),
);
