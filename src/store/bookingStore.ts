import { create } from "zustand";
import type {
  BookingStep,
  CustomerInfo,
  CustomField,
  CustomerValue,
  Extra,
  Package,
  PriceBreakdownItem,
  ResourceFootprint,
  SelectedExtra,
  Space,
  SelectionItem,
} from "../types";

import { checkCartHasBooking, fetchResourceMap } from "../utils/api";

interface BookingState {
  bookingPolicy: string;
  currentStep: BookingStep;
  selectedItems: SelectionItem[];
  lockedResourceIds: number[]; // Cached union footprint for UI
  resourceMap: Record<number, ResourceFootprint> | null;
  selectedDate: string;
  selectedStartTime: string;
  selectedEndTime: string;
  availableExtras: Extra[];
  selectedExtras: SelectedExtra[];
  customerInfo: CustomerInfo;
  customerFields: CustomField[];
  checkoutUrl: string | null;
  bookingId: number | null;
  bookingStatus: "pending" | "in_review" | "error";
  totalPrice: number;
  priceBreakdown: PriceBreakdownItem[];
  isConfirmed: boolean;
  hasCartBooking: boolean;
  setStep: (step: BookingStep) => void;
  nextStep: () => void;
  prevStep: () => void;
  addItem: (item: SelectionItem) => void;
  removeItem: (id: number) => void;
  clearItems: () => void;
  getLockedResourceIds: () => number[];
  loadResourceMap: () => Promise<void>;
  setDate: (date: string) => void;
  setStartTime: (time: string) => void;
  setEndTime: (time: string) => void;
  setAvailableExtras: (extras: Extra[]) => void;
  toggleItem: (item: Space | Package) => void;
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
  setBookingStatus: (status: "pending" | "in_review" | "error") => void;
  setHasCartBooking: (has: boolean) => void;
  reset: () => void;
  setBookingPolicy: (policy: string) => void;
}

const DEFAULT_CUSTOMER: CustomerInfo = {};

export const useBookingStore = create<BookingState>()((set, get) => ({
  // ── Initial state ────────────────────────────────────────────────────────
  currentStep: 1,
  bookingPolicy: "",
  selectedItems: [],
  lockedResourceIds: [],
  resourceMap: null,
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

  loadResourceMap: async () => {
    console.log("loadResourceMap called");
    try {
      const map = await fetchResourceMap();
      console.log("resourceMap loaded:", Object.keys(map));
      set({ resourceMap: map });
    } catch (e) {
      console.error("Failed to load resource map:", e);
    }
  },

  // ── Step 1 ───────────────────────────────────────────────────────────────
  addItem: (item: SelectionItem) => {
    console.log("addItem called:", item.id, item.title);
    const state = get();
    console.log(
      "current selectedItems:",
      state.selectedItems.map((i) => i.id),
    );
    console.log("resourceMap loaded?", !!state.resourceMap);
    console.log("current locked:", state.lockedResourceIds);
    if (state.selectedItems.some((i) => i.id === item.id)) {
      console.log("already selected, return");
      return;
    }
    if (!state.resourceMap) {
      console.log("no resourceMap, alert");
      alert("Resource map loading...");
      return;
    }
    const map = state.resourceMap;
    const itemFootprint = map[item.id]?.footprint ?? [item.id];
    console.log("itemFootprint:", itemFootprint);
    const currentLocked = state.lockedResourceIds;
    const hasOverlap = itemFootprint.some((id) => currentLocked.includes(id));
    console.log("hasOverlap?", hasOverlap);
    if (hasOverlap) {
      console.log("overlap, alert");
      alert("Conflicts with current selection: overlaps physical resources.");
      return;
    }
    const computeLocked = (items: SelectionItem[]): number[] => {
      const locked = new Set<number>();
      for (const it of items) {
        const footprint = map[it.id]?.footprint ?? [it.id];
        footprint.forEach((id) => locked.add(id));
      }
      return Array.from(locked);
    };
    const newSelected = [...state.selectedItems, item];
    const newLocked = computeLocked(newSelected);
    console.log(
      "setting new selected:",
      newSelected.map((i) => i.id),
      "new locked:",
      newLocked,
    );
    set({ selectedItems: newSelected, lockedResourceIds: newLocked });
    console.log("addItem done");
  },
  removeItem: (id: number) => {
    console.log("removeItem called:", id);
    const state = get();
    console.log(
      "current selectedItems:",
      state.selectedItems.map((i) => i.id),
    );
    console.log("current locked:", state.lockedResourceIds);
    if (!state.resourceMap) {
      console.log("no resourceMap, return");
      return;
    }
    const map = state.resourceMap;
    const computeLocked = (items: SelectionItem[]): number[] => {
      const locked = new Set<number>();
      for (const it of items) {
        const footprint = map[it.id]?.footprint ?? [it.id];
        footprint.forEach((id) => locked.add(id));
      }
      return Array.from(locked);
    };
    const newSelected = state.selectedItems.filter((i) => i.id !== id);
    const newLocked = computeLocked(newSelected);
    console.log(
      "setting new selected:",
      newSelected.map((i) => i.id),
      "new locked:",
      newLocked,
    );
    set({ selectedItems: newSelected, lockedResourceIds: newLocked });
    console.log("removeItem done");
  },

  // Unified toggle function for cards/checkboxes
  toggleItem: (item: Space | Package) => {
    const targetId = Number(item.id);
    const state = get();
    const isSelected = state.selectedItems.some(
      (i) => Number(i.id) === targetId,
    );

    if (isSelected) {
      // 1. IMMUTABLE REMOVAL
      const updatedItems = state.selectedItems.filter(
        (i) => Number(i.id) !== targetId,
      );
      const computeLocked = (items: SelectionItem[]): number[] => {
        const map = state.resourceMap;
        if (!map) return [];
        const locked = new Set<number>();
        for (const it of items) {
          const footprint = map[it.id]?.footprint ?? [it.id];
          footprint.forEach((id) => locked.add(id));
        }
        return Array.from(locked);
      };
      const newLocked = computeLocked(updatedItems);
      set({ selectedItems: updatedItems, lockedResourceIds: newLocked });

      console.log(`Unselected: ${targetId}. Re-computing locks...`);
    } else {
      // 3. VALIDATED ADDITION
      if (!state.resourceMap) {
        alert("Resource map loading...");
        return;
      }
      const map = state.resourceMap;
      const itemFootprint = map[targetId]?.footprint ?? [targetId];
      const hasOverlap = itemFootprint.some((id) =>
        state.lockedResourceIds.includes(id),
      );
      if (hasOverlap) {
        console.warn(
          "Cannot add: Item is physically locked by another selection.",
        );
        alert("Conflicts with current selection: overlaps physical resources.");
        return;
      }
      const typedItem: SelectionItem = (
        "space_ids" in item
          ? { ...item, type: "package" as const }
          : { ...item, type: "space" as const }
      ) as SelectionItem;
      const updatedItems = [...state.selectedItems, typedItem];
      const computeLocked = (items: SelectionItem[]): number[] => {
        const locked = new Set<number>();
        for (const it of items) {
          const footprint = map[it.id]?.footprint ?? [it.id];
          footprint.forEach((id) => locked.add(id));
        }
        return Array.from(locked);
      };
      const newLocked = computeLocked(updatedItems);
      set({ selectedItems: updatedItems, lockedResourceIds: newLocked });

      console.log(`Selected: ${targetId}. Updating locks...`);
    }
  },
  clearItems: () => set({ selectedItems: [], lockedResourceIds: [] }),
  getPrimarySpaceId: () => {
    const state = get();
    // FIXED-PRIORITY: First locked physical space ID, then first selected item ID
    if (state.lockedResourceIds.length > 0) {
      return state.lockedResourceIds[0];
    }
    if (state.selectedItems.length > 0) {
      return state.selectedItems[0].id;
    }
    return 0;
  },
  getLockedResourceIds: () => {
    const state = get();
    if (!state.resourceMap) return [];
    const map = state.resourceMap;
    const computeLocked = (items: SelectionItem[]): number[] => {
      const locked = new Set<number>();
      for (const it of items) {
        const footprint = map[it.id]?.footprint ?? [it.id];
        footprint.forEach((id) => locked.add(id));
      }
      return Array.from(locked);
    };
    return computeLocked(state.selectedItems);
  },

  setSpace: (space: Space | null) => {
    if (space) {
      get().addItem({
        ...space,
        type: "space" as const,
      });
    } else {
      get().clearItems();
    }
  },
  setPackage: (pkg: Package | null) => {
    if (pkg) {
      get().addItem({
        ...pkg,
        type: "package" as const,
      });
    } else {
      get().clearItems();
    }
  },

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
  setCustomerFields: (fields: CustomField[]) => set({ customerFields: fields }),
  fetchCustomerFields: async () => {
    try {
      const res = await fetch(`${window.sbConfig.apiBase}/customer/fields/`, {
        headers: { "X-WP-Nonce": window.sbConfig.nonce },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      console.log("Customer fields:", data);
      if (data.fields && Array.isArray(data.fields) && data.fields.length > 0) {
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
  }) => set({ checkoutUrl, bookingId, totalPrice, priceBreakdown: breakdown }),

  setPriceBreakdown: (rawBreakdown: PriceBreakdownItem[], total: number) => {
    const state = get();
    const breakdown = rawBreakdown.map((item: PriceBreakdownItem) => {
      let label = item.label;
      const pkgItem = state.selectedItems.find((i) => i.type === "package");
      if (label === "Package price" && pkgItem) {
        label = `${pkgItem.title}`;
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
        state.selectedItems.some((i) => i.type === "space") &&
        (label.includes("–") || label.match(/^\\d{2}:\\d{2}–\\d{2}:\\d{2}/))
      ) {
        const primarySpace = state.selectedItems.find(
          (i) => i.type === "space",
        ) as Space;
        label = `${primarySpace.title}: ${label}`;
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

  setHasCartBooking: (has: boolean) => set({ hasCartBooking: has }),

  // ── Booking Status ───────────────────────────────────────────────────────
  loadBookingStatus: async (id: number) => {
    try {
      const res = await fetch(`${window.sbConfig.apiBase}/bookings/${id}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      const status = data.status || data.booking?.status || "error";
      set({ bookingStatus: status as "pending" | "in_review" | "error" });
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

  setBookingStatus: (status: "pending" | "in_review" | "error") =>
    set({ bookingStatus: status }),

  reset: () => {
    set({
      currentStep: 1,
      bookingPolicy: "",
      selectedItems: [],
      lockedResourceIds: [],
      resourceMap: null,
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
}));
