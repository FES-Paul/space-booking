import { beforeEach, describe, expect, it, vi } from "vitest";
import type { SelectionItem } from "@/types";

const selectedPackage: SelectionItem = {
  id: 55,
  title: "Celebration Package",
  description: "Package description",
  thumbnail: null,
  price: 2500,
  duration: 2,
  space_id: 11,
  space_name: "Studio A",
  extra_ids: [7],
  thirty_min_extension_enabled: true,
  thirty_min_extension_price: 350,
  space_ids: [11, 12],
  type: "package",
};

const selectedSpace: SelectionItem = {
  id: 99,
  title: "Studio B",
  description: "Standalone space",
  excerpt: "",
  thumbnail: null,
  hourly_rate: 1200,
  min_duration: 1,
  max_duration: 8,
  capacity: 20,
  day_overrides: {},
  price_overrides: null,
  gallery: [],
  thirty_min_extension_enabled: false,
  thirty_min_extension_price: 0,
  type: "space",
};

const draftPayload = {
  currentStep: 6 as const,
  selectedItems: [selectedPackage],
  selectedDate: "2026-06-28",
  selectedSlotWindows: [
    {
      slotId: "slot_1",
      start: "10:00",
      end: "12:00",
    },
  ],
  selectedStartTime: "10:00",
  selectedEndTime: "12:00",
  hasThirtyMinuteExtension: true,
  selectedExtras: [{ extra_id: 7, quantity: 2 }],
  customerInfo: {
    name: "Alice Example",
    email: "alice@example.com",
    phone: "09123456789",
    notes: "Needs projector",
  },
  packageQuestionAnswers: {
    pkg_55__theme: {
      value: "Pastel",
    },
  },
};

const loadStoreModule = async () => import("@/store/bookingStore");

describe("bookingStore draft persistence", () => {
  beforeEach(() => {
    vi.resetModules();
    localStorage.clear();
    document.body.innerHTML =
      '<div id="sb-booking-app" data-space-id="11"></div>';
    window.history.replaceState({}, "", "/booking");
  });

  it("restores a saved draft for the same booking page", async () => {
    const initialModule = await loadStoreModule();
    const initialStore = initialModule.useBookingStore;
    const storageKey = initialModule.getBookingDraftStorageKey();

    initialStore.setState(draftPayload);

    expect(localStorage.getItem(storageKey)).toContain("Alice Example");

    vi.resetModules();
    const restoredModule = await loadStoreModule();
    const restoredStore = restoredModule.useBookingStore;

    expect(restoredStore.getState().hydrateDraft()).toBe(true);

    const state = restoredStore.getState();
    expect(state.currentStep).toBe(6);
    expect(state.selectedItems).toEqual([selectedPackage]);
    expect(state.packageCoverage).toEqual([
      {
        packageId: 55,
        packageTitle: "Celebration Package",
        coveredSpaceIds: [11, 12],
      },
    ]);
    expect(state.selectedDate).toBe("2026-06-28");
    expect(state.selectedStartTime).toBe("10:00");
    expect(state.selectedEndTime).toBe("12:00");
    expect(state.hasThirtyMinuteExtension).toBe(true);
    expect(state.selectedExtras).toEqual([{ extra_id: 7, quantity: 2 }]);
    expect(state.customerInfo).toMatchObject({
      name: "Alice Example",
      email: "alice@example.com",
      phone: "09123456789",
      notes: "Needs projector",
    });
    expect(state.packageQuestionAnswers).toEqual({
      pkg_55__theme: {
        value: "Pastel",
      },
    });
  });

  it("keeps the saved draft when checkout data exists but the Woo cart handoff has not happened yet", async () => {
    const storeModule = await loadStoreModule();
    const store = storeModule.useBookingStore;
    const storageKey = storeModule.getBookingDraftStorageKey();

    store.setState(draftPayload);
    expect(localStorage.getItem(storageKey)).not.toBeNull();

    store.getState().setCheckoutData({
      checkoutUrl: "/checkout/order-pay/123",
      bookingId: 123,
      totalPrice: 2500,
      breakdown: [],
    });

    expect(localStorage.getItem(storageKey)).not.toBeNull();
  });

  it("clears the saved draft once the booking is in the WooCommerce cart", async () => {
    const storeModule = await loadStoreModule();
    const store = storeModule.useBookingStore;
    const storageKey = storeModule.getBookingDraftStorageKey();

    store.setState(draftPayload);
    expect(localStorage.getItem(storageKey)).not.toBeNull();

    store.getState().setHasCartBooking(true);

    expect(localStorage.getItem(storageKey)).toBeNull();
  });

  it("falls back to step 2 when a restored draft is missing schedule data", async () => {
    const storeModule = await loadStoreModule();
    const storageKey = storeModule.getBookingDraftStorageKey();

    localStorage.setItem(
      storageKey,
      JSON.stringify({
        version: 1,
        currentStep: 6,
        selectedItems: [selectedPackage],
        selectedDate: "",
        selectedSlotWindows: [],
        selectedStartTime: "",
        selectedEndTime: "",
        selectedExtras: [],
        customerInfo: {},
        packageQuestionAnswers: {},
      }),
    );

    expect(storeModule.useBookingStore.getState().hydrateDraft()).toBe(true);
    expect(storeModule.useBookingStore.getState().currentStep).toBe(2);
  });

  it("does not persist confirmation-step state", async () => {
    const storeModule = await loadStoreModule();
    const store = storeModule.useBookingStore;
    const storageKey = storeModule.getBookingDraftStorageKey();

    store.setState({
      ...draftPayload,
      currentStep: 7,
    });

    expect(localStorage.getItem(storageKey)).toBeNull();
  });

  it("clears the 30-minute extension when the schedule changes", async () => {
    const storeModule = await loadStoreModule();
    const store = storeModule.useBookingStore;

    store.setState(draftPayload);
    expect(store.getState().hasThirtyMinuteExtension).toBe(true);

    store.getState().setDate("2026-06-29");

    const state = store.getState();
    expect(state.selectedDate).toBe("2026-06-29");
    expect(state.hasThirtyMinuteExtension).toBe(false);
  });

  it("drops the 30-minute extension when a non-supported item is added", async () => {
    const storeModule = await loadStoreModule();
    const store = storeModule.useBookingStore;

    store.setState(draftPayload);
    expect(store.getState().hasThirtyMinuteExtension).toBe(true);

    store.getState().addItem(selectedSpace);

    const state = store.getState();
    expect(state.selectedItems).toEqual([selectedPackage, selectedSpace]);
    expect(state.hasThirtyMinuteExtension).toBe(false);
  });

  it("removes package-linked extras and answers while keeping unrelated review data", async () => {
    const storeModule = await loadStoreModule();
    const store = storeModule.useBookingStore;

    store.setState({
      ...draftPayload,
      selectedItems: [selectedPackage, selectedSpace],
      selectedExtras: [
        { extra_id: 7, quantity: 2, included: true },
        { extra_id: 21, quantity: 1, included: false },
      ],
      priceBreakdown: [
        { label: "Celebration Package", amount: 2500 },
        { label: "Additional projector", amount: 300 },
      ],
      totalPrice: 2800,
    });

    store.getState().removeItem(55);

    const state = store.getState();
    expect(state.currentStep).toBe(6);
    expect(state.selectedItems).toEqual([selectedSpace]);
    expect(state.selectedExtras).toEqual([{ extra_id: 21, quantity: 1, included: false }]);
    expect(state.packageQuestionAnswers).toEqual({});
    expect(state.priceBreakdown).toEqual([]);
    expect(state.totalPrice).toBe(0);
  });

  it("returns to step 1 and clears booking-specific selections when the last item is removed", async () => {
    const storeModule = await loadStoreModule();
    const store = storeModule.useBookingStore;

    store.setState({
      ...draftPayload,
      selectedExtras: [{ extra_id: 7, quantity: 2, included: true }],
      priceBreakdown: [{ label: "Celebration Package", amount: 2500 }],
      totalPrice: 2500,
    });

    store.getState().removeItem(55);

    const state = store.getState();
    expect(state.currentStep).toBe(1);
    expect(state.selectedItems).toEqual([]);
    expect(state.selectedDate).toBe("");
    expect(state.selectedSlotWindows).toEqual([]);
    expect(state.selectedStartTime).toBe("");
    expect(state.selectedEndTime).toBe("");
    expect(state.selectedExtras).toEqual([]);
    expect(state.packageQuestionAnswers).toEqual({});
  });

  it("removes only the paid portion of an included extra from review", async () => {
    const storeModule = await loadStoreModule();
    const store = storeModule.useBookingStore;

    store.setState({
      ...draftPayload,
      selectedExtras: [{ extra_id: 7, quantity: 3, included: true }],
      priceBreakdown: [{ label: "Additional balloons", amount: 400 }],
      totalPrice: 2900,
    });

    store.getState().removeReviewExtra(7);

    const state = store.getState();
    expect(state.selectedExtras).toEqual([{ extra_id: 7, quantity: 1, included: true }]);
    expect(state.priceBreakdown).toEqual([]);
    expect(state.totalPrice).toBe(0);
  });

  it("removes a standalone extra entirely from review", async () => {
    const storeModule = await loadStoreModule();
    const store = storeModule.useBookingStore;

    store.setState({
      ...draftPayload,
      selectedExtras: [{ extra_id: 21, quantity: 1, included: false }],
      packageQuestionAnswers: {},
      priceBreakdown: [{ label: "Projector", amount: 250 }],
      totalPrice: 2750,
    });

    store.getState().removeReviewExtra(21);

    const state = store.getState();
    expect(state.selectedExtras).toEqual([]);
    expect(state.priceBreakdown).toEqual([]);
    expect(state.totalPrice).toBe(0);
  });
});
