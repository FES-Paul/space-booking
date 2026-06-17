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
  space_ids: [11, 12],
  type: "package",
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
});
