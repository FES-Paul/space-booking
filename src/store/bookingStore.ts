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
  SelectedSlotWindow,
  SelectedExtra,
  Space,
  SelectionItem,
  PackageQuestionAnswerValue,
} from "../types";

import { checkCartHasBooking, fetchResourceMap } from "../utils/api";
import { getSelectedSlotSpan } from "../utils/slotSelection";

// New: Track which spaces are covered by selected packages
interface PackageCoverage {
  packageId: number;
  packageTitle: string;
  coveredSpaceIds: number[];
}

type SelectedPackageItem = Extract<SelectionItem, { type: "package" }>;

// Helper: Compute locked resource IDs from selected items
const computeLockedResourceIds = (
  items: SelectionItem[],
  resourceMap: Record<number, ResourceFootprint> | null,
): number[] => {
  if (!resourceMap) return [];
  const locked = new Set<number>();
  for (const it of items) {
    const footprint = resourceMap[it.id]?.footprint ?? [it.id];
    footprint.forEach((id) => locked.add(id));
  }
  return Array.from(locked);
};

interface PersistedBookingDraft {
  version: 1;
  currentStep: BookingStep;
  selectedItems: SelectionItem[];
  selectedDate: string;
  selectedSlotWindows: SelectedSlotWindow[];
  selectedStartTime: string;
  selectedEndTime: string;
  selectedExtras: SelectedExtra[];
  customerInfo: CustomerInfo;
  packageQuestionAnswers: Record<string, PackageQuestionAnswerValue>;
}

const DRAFT_STORAGE_PREFIX = "sb-booking-draft:";
const MAX_DRAFT_STEP: BookingStep = 6;

interface BookingState {
  bookingPolicy: string;
  currentStep: BookingStep;
  selectedItems: SelectionItem[];
  lockedResourceIds: number[]; // Cached union footprint for UI
  resourceMap: Record<number, ResourceFootprint> | null;
  packageCoverage: PackageCoverage[]; // NEW: Track packages and their covered spaces
  selectedDate: string;
  selectedSlotWindows: SelectedSlotWindow[];
  selectedStartTime: string;
  selectedEndTime: string;
  availableExtras: Extra[];
  selectedExtras: SelectedExtra[];
  customerInfo: CustomerInfo;
  packageQuestionAnswers: Record<string, PackageQuestionAnswerValue>;
  customerFields: CustomField[];
  checkoutUrl: string | null;
  bookingId: number | null;
  bookingStatus: "pending" | "in_review" | "error";
  totalPrice: number;
  priceBreakdown: PriceBreakdownItem[];
  extrasDetails: import("@/types").ExtraDetail[]; // From backend pricing response
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
  setSelectedSlotWindows: (slots: SelectedSlotWindow[]) => void;
  setStartTime: (time: string) => void;
  setEndTime: (time: string) => void;
  setAvailableExtras: (extras: Extra[]) => void;
  setSelectedExtras: (extras: SelectedExtra[]) => void;
  setIncludedExtras: (extraIds: number[]) => void;
  toggleItem: (item: Space | Package) => void;
  toggleExtra: (extra_id: number, quantity?: number, included?: boolean) => void;
  removeReviewExtra: (extra_id: number) => void;
  incrementExtra: (extra_id: number) => void;
  decrementExtra: (extra_id: number) => void;
  setCustomerField: (key: string, value: CustomerValue) => void;
  setPackageQuestionAnswer: (
    answerKey: string,
    value: string | number | string[],
    othersText?: string,
  ) => void;
  clearPackageQuestionAnswers: () => void;
  hasPackageQuestionsStep: () => boolean;
  setCustomerFields: (fields: CustomField[]) => void;
  fetchCustomerFields: () => Promise<void>;
  validateCustomerInfo: () => boolean;
  setCheckoutData: (data: {
    checkoutUrl: string;
    bookingId: number;
    totalPrice: number;
    breakdown: PriceBreakdownItem[];
  }) => void;
  setPriceBreakdown: (breakdown: PriceBreakdownItem[], total: number, extrasDetails?: import("@/types").ExtraDetail[]) => void;
  confirmBooking: () => void;
  checkCartBooking: () => Promise<void>;
  loadBookingStatus: (id: number) => Promise<void>;
  setBookingStatus: (status: "pending" | "in_review" | "error") => void;
  getPrimarySpaceId: () => number | null;
  getAllSpaceIds: () => number[];
  getAllPackageIds: () => number[];
  getCoveredSpaceIds: () => number[];
  setHasCartBooking: (has: boolean) => void;
  hydrateDraft: () => boolean;
  clearDraft: () => void;
  reset: () => void;
  setBookingPolicy: (policy: string) => void;
  getMergedExtras: () => MergedExtra[];
}

// NEW: Type for merged extras (UI adapter)
export interface MergedExtra {
  extra_id: number;
  title: string;
  total_qty: number;
  included_qty: number;
  paid_qty: number;
  unit_price: number;
  is_locked: boolean;
}

const DEFAULT_CUSTOMER: CustomerInfo = {};

const createInitialBookingState = () => ({
  currentStep: 1 as BookingStep,
  bookingPolicy: "",
  selectedItems: [] as SelectionItem[],
  lockedResourceIds: [] as number[],
  resourceMap: null as Record<number, ResourceFootprint> | null,
  packageCoverage: [] as PackageCoverage[],
  selectedDate: "",
  selectedSlotWindows: [] as SelectedSlotWindow[],
  selectedStartTime: "",
  selectedEndTime: "",
  availableExtras: [] as Extra[],
  selectedExtras: [] as SelectedExtra[],
  customerInfo: { ...DEFAULT_CUSTOMER },
  packageQuestionAnswers: {} as Record<string, PackageQuestionAnswerValue>,
  customerFields: [] as CustomField[],
  checkoutUrl: null as string | null,
  bookingId: null as number | null,
  bookingStatus: "pending" as const,
  totalPrice: 0,
  priceBreakdown: [] as PriceBreakdownItem[],
  extrasDetails: [] as import("@/types").ExtraDetail[],
  isConfirmed: false,
  hasCartBooking: false,
});

const isPackageSelection = (item: SelectionItem): item is SelectedPackageItem =>
  item.type === "package";

const buildPackageCoverage = (items: SelectionItem[]): PackageCoverage[] =>
  items
    .filter(isPackageSelection)
    .map((pkg) => {
      const coveredSpaceIds = Array.isArray(pkg.space_ids) && pkg.space_ids.length > 0
        ? pkg.space_ids.map((id) => Number(id)).filter((id) => id > 0)
        : pkg.space_id
          ? [Number(pkg.space_id)]
          : [];

      return {
        packageId: Number(pkg.id),
        packageTitle: pkg.title || "Package",
        coveredSpaceIds,
      };
    })
    .filter((pkg) => pkg.coveredSpaceIds.length > 0);

const getPackageExtraIds = (pkg: SelectedPackageItem): number[] =>
  Array.isArray(pkg.extra_ids)
    ? pkg.extra_ids.map((id) => Number(id)).filter((id) => id > 0)
    : [];

const getIncludedExtraQtyMap = (items: SelectionItem[]): Map<number, number> => {
  const includedQtyMap = new Map<number, number>();

  items.filter(isPackageSelection).forEach((pkg) => {
    getPackageExtraIds(pkg).forEach((extraId) => {
      const current = includedQtyMap.get(extraId) ?? 0;
      includedQtyMap.set(extraId, Math.max(current, 1));
    });
  });

  return includedQtyMap;
};

const getPackageIdFromAnswerKey = (answerKey: string): number | null => {
  const match = /^pkg_(\d+)__/.exec(answerKey);
  if (!match) return null;

  const packageId = Number(match[1]);
  return Number.isFinite(packageId) ? packageId : null;
};

const stripPackageQuestionAnswers = (
  answers: Record<string, PackageQuestionAnswerValue>,
  removedPackageIds: Set<number>,
): Record<string, PackageQuestionAnswerValue> =>
  Object.fromEntries(
    Object.entries(answers).filter(([answerKey]) => {
      const packageId = getPackageIdFromAnswerKey(answerKey);
      return packageId === null || !removedPackageIds.has(packageId);
    }),
  );

const hasPackageQuestionEntries = (items: SelectionItem[]): boolean =>
  items.some((item) => {
    if (!isPackageSelection(item)) return false;
    return Array.isArray(item.theme_meta_fields) && item.theme_meta_fields.length > 0;
  });

type SelectionRemovalContext = Pick<
  BookingState,
  | "currentStep"
  | "selectedItems"
  | "resourceMap"
  | "selectedExtras"
  | "packageQuestionAnswers"
>;

const createSelectionRemovalPatch = (
  state: SelectionRemovalContext,
  itemId: number,
): Partial<BookingState> => {
  const removedItem = state.selectedItems.find((item) => Number(item.id) === Number(itemId));
  if (!removedItem) return {};

  const selectedItems = state.selectedItems.filter(
    (item) => Number(item.id) !== Number(itemId),
  );
  const packageCoverage = buildPackageCoverage(selectedItems);
  const lockedResourceIds = computeLockedResourceIds(selectedItems, state.resourceMap);

  const removedPackageIds = new Set<number>();
  const removedPackageExtraIds = new Set<number>();

  if (isPackageSelection(removedItem)) {
    removedPackageIds.add(Number(removedItem.id));
    getPackageExtraIds(removedItem).forEach((extraId) => {
      removedPackageExtraIds.add(extraId);
    });
  }

  const remainingIncludedExtraQty = getIncludedExtraQtyMap(selectedItems);
  const selectedExtras = state.selectedExtras.flatMap((selectedExtra) => {
    if (!removedPackageExtraIds.has(selectedExtra.extra_id)) {
      return [selectedExtra];
    }

    const includedQty = remainingIncludedExtraQty.get(selectedExtra.extra_id) ?? 0;
    if (includedQty > 0) {
      return [
        {
          extra_id: selectedExtra.extra_id,
          quantity: includedQty,
          included: true,
        },
      ];
    }

    return [];
  });

  const packageQuestionAnswers = stripPackageQuestionAnswers(
    state.packageQuestionAnswers,
    removedPackageIds,
  );

  if (selectedItems.length === 0) {
    return {
      currentStep: 1,
      selectedItems,
      lockedResourceIds,
      packageCoverage,
      selectedDate: "",
      selectedSlotWindows: [],
      selectedStartTime: "",
      selectedEndTime: "",
      availableExtras: [],
      selectedExtras: [],
      packageQuestionAnswers: {},
      checkoutUrl: null,
      bookingId: null,
      totalPrice: 0,
      priceBreakdown: [],
      extrasDetails: [],
    };
  }

  const nextStep =
    state.currentStep === 4 && !hasPackageQuestionEntries(selectedItems)
      ? 5
      : state.currentStep;

  return {
    currentStep: nextStep,
    selectedItems,
    lockedResourceIds,
    packageCoverage,
    selectedExtras,
    packageQuestionAnswers,
    checkoutUrl: null,
    bookingId: null,
    totalPrice: 0,
    priceBreakdown: [],
    extrasDetails: [],
  };
};

const hasCustomerInfo = (customerInfo: CustomerInfo): boolean =>
  Object.values(customerInfo).some((value) => {
    if (Array.isArray(value)) return value.length > 0;
    if (typeof value === "boolean") return value;
    return String(value ?? "").trim().length > 0;
  });

const isBrowser = (): boolean =>
  typeof window !== "undefined" && typeof window.localStorage !== "undefined";

export const getBookingDraftStorageKey = (): string => {
  if (!isBrowser()) {
    return `${DRAFT_STORAGE_PREFIX}server`;
  }

  const appEl = document.getElementById("sb-booking-app") as HTMLElement | null;
  const path = window.location.pathname.replace(/\/+$/, "") || "/";
  const spaceId = appEl?.dataset.spaceId || "all";
  const packageId = appEl?.dataset.packageId || "all";

  return `${DRAFT_STORAGE_PREFIX}${path}::space:${spaceId}::package:${packageId}`;
};

const clearStoredDraft = (): void => {
  if (!isBrowser()) return;

  try {
    window.localStorage.removeItem(getBookingDraftStorageKey());
  } catch (error) {
    console.error("Failed to clear booking draft:", error);
  }
};

const getPersistedDraft = (state: BookingState): PersistedBookingDraft => ({
  version: 1,
  currentStep:
    state.currentStep > MAX_DRAFT_STEP ? MAX_DRAFT_STEP : state.currentStep,
  selectedItems: state.selectedItems,
  selectedDate: state.selectedDate,
  selectedSlotWindows: state.selectedSlotWindows,
  selectedStartTime: state.selectedStartTime,
  selectedEndTime: state.selectedEndTime,
  selectedExtras: state.selectedExtras,
  customerInfo: state.customerInfo,
  packageQuestionAnswers: state.packageQuestionAnswers,
});

const hasDraftContent = (draft: PersistedBookingDraft): boolean =>
  draft.selectedItems.length > 0 ||
  draft.selectedDate.length > 0 ||
  draft.selectedSlotWindows.length > 0 ||
  draft.selectedStartTime.length > 0 ||
  draft.selectedEndTime.length > 0 ||
  draft.selectedExtras.length > 0 ||
  hasCustomerInfo(draft.customerInfo) ||
  Object.keys(draft.packageQuestionAnswers).length > 0;

const persistDraft = (state: BookingState): void => {
  if (!isBrowser()) return;

  const draft = getPersistedDraft(state);

  if (
    state.currentStep >= 7 ||
    state.isConfirmed ||
    state.hasCartBooking ||
    !hasDraftContent(draft)
  ) {
    clearStoredDraft();
    return;
  }

  try {
    window.localStorage.setItem(
      getBookingDraftStorageKey(),
      JSON.stringify(draft),
    );
  } catch (error) {
    console.error("Failed to persist booking draft:", error);
  }
};

const parseDraft = (raw: string | null): PersistedBookingDraft | null => {
  if (!raw) return null;

  try {
    const parsed = JSON.parse(raw) as Partial<PersistedBookingDraft>;
    if (parsed.version !== 1) return null;
    if (!Array.isArray(parsed.selectedItems)) return null;
    if (!Array.isArray(parsed.selectedSlotWindows)) return null;
    if (!Array.isArray(parsed.selectedExtras)) return null;
    if (!parsed.customerInfo || typeof parsed.customerInfo !== "object") return null;
    if (
      !parsed.packageQuestionAnswers ||
      typeof parsed.packageQuestionAnswers !== "object"
    ) {
      return null;
    }

    const currentStep =
      typeof parsed.currentStep === "number"
        ? (Math.min(Math.max(parsed.currentStep, 1), MAX_DRAFT_STEP) as BookingStep)
        : 1;

    return {
      version: 1,
      currentStep,
      selectedItems: parsed.selectedItems as SelectionItem[],
      selectedDate:
        typeof parsed.selectedDate === "string" ? parsed.selectedDate : "",
      selectedSlotWindows:
        parsed.selectedSlotWindows as SelectedSlotWindow[],
      selectedStartTime:
        typeof parsed.selectedStartTime === "string"
          ? parsed.selectedStartTime
          : "",
      selectedEndTime:
        typeof parsed.selectedEndTime === "string" ? parsed.selectedEndTime : "",
      selectedExtras: parsed.selectedExtras as SelectedExtra[],
      customerInfo: parsed.customerInfo as CustomerInfo,
      packageQuestionAnswers:
        parsed.packageQuestionAnswers as Record<
          string,
          PackageQuestionAnswerValue
        >,
    };
  } catch (error) {
    console.error("Failed to parse booking draft:", error);
    return null;
  }
};

const getRestorableStep = (draft: PersistedBookingDraft): BookingStep => {
  if (draft.selectedItems.length === 0) {
    return 1;
  }

  if (!draft.selectedDate || !draft.selectedStartTime || !draft.selectedEndTime) {
    return draft.currentStep > 2 ? 2 : draft.currentStep;
  }

  return draft.currentStep;
};

export const useBookingStore = create<BookingState>()((set, get) => ({
  // ── Initial state ────────────────────────────────────────────────────────
  ...createInitialBookingState(),

  // ── Navigation ───────────────────────────────────────────────────────────
  setStep: (step: BookingStep) => set({ currentStep: step }),
  nextStep: () =>
    set((state) => {
      const hasPackageQuestions = get().hasPackageQuestionsStep();
      let next: BookingStep = state.currentStep;
      if (state.currentStep === 1) next = 2;
      else if (state.currentStep === 2) next = 3;
      else if (state.currentStep === 3) next = hasPackageQuestions ? 4 : 5;
      else if (state.currentStep === 4) next = 5;
      else if (state.currentStep === 5) next = 6;
      else if (state.currentStep === 6) next = 7;
      return { currentStep: next };
    }),
  prevStep: () =>
    set((state) => {
      const hasPackageQuestions = get().hasPackageQuestionsStep();
      let prev: BookingStep = state.currentStep;
      if (state.currentStep === 7) prev = 6;
      else if (state.currentStep === 6) prev = 5;
      else if (state.currentStep === 5) prev = hasPackageQuestions ? 4 : 3;
      else if (state.currentStep === 4) prev = 3;
      else if (state.currentStep === 3) prev = 2;
      else if (state.currentStep === 2) prev = 1;
      return { currentStep: prev };
    }),

  loadResourceMap: async () => {
    console.log("loadResourceMap called");
    try {
      const map = await fetchResourceMap();
      console.log("resourceMap loaded, keys:", Object.keys(map));
      // Log package footprints
      for (const [id, data] of Object.entries(map)) {
        if (data.type === 'package') {
          console.log("  Package", id, "footprint:", data.footprint);
        }
      }
      set({
        resourceMap: map,
        lockedResourceIds: computeLockedResourceIds(get().selectedItems, map),
        packageCoverage: buildPackageCoverage(get().selectedItems),
      });
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
    const newSelected = [...state.selectedItems, item];
    const newLocked = computeLockedResourceIds(newSelected, map);
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
    const nextState = createSelectionRemovalPatch(state, id);
    console.log(
      "setting new selected:",
      (nextState.selectedItems ?? []).map((i) => i.id),
      "new locked:",
      nextState.lockedResourceIds ?? [],
    );
    set(nextState);
    console.log("removeItem done");
  },

  // Unified toggle function for cards/checkboxes
  toggleItem: (item: Space | Package) => {
    const targetId = Number(item.id);
    const state = get();
    const isSelected = state.selectedItems.some(
      (i) => Number(i.id) === targetId,
    );

    console.log("🔄 toggleItem called:", targetId, "title:", item.title, "isSelected:", isSelected);
    console.log("  current packageCoverage:", state.packageCoverage);
    console.log("  current selectedItems:", state.selectedItems.map(i => i.id));

    const isPackage = "space_id" in item || ("space_ids" in item && Array.isArray(item.space_ids));
    const packageSpaceIds = isPackage
      ? Array.isArray(item.space_ids) && item.space_ids.length > 0
        ? item.space_ids.map((spaceId) => Number(spaceId)).filter((spaceId) => spaceId > 0)
        : "space_id" in item && item.space_id
          ? [Number(item.space_id)]
          : []
      : [];
    const itemTitle = item.title || "Item";
    
    console.log("  isPackage:", isPackage, "space_ids:", packageSpaceIds);

    if (isSelected) {
      set(createSelectionRemovalPatch(state, targetId));

      console.log(`Unselected: ${targetId}. Re-computing locks...`);
    } else {
      // ADDITION - with mutual exclusivity checks
      if (!state.resourceMap) {
        alert("Resource map loading...");
        return;
      }
      const map = state.resourceMap;
      
      // Check 1: If adding a SPACE, check if it's covered by any selected package
      if (!isPackage) {
        const coveredByPackage = state.packageCoverage.find((pc) =>
          pc.coveredSpaceIds.includes(targetId)
        );
        if (coveredByPackage) {
          console.warn(
            `Cannot select this space. It is already included in package "${coveredByPackage.packageTitle}".`,
          );
          alert(
            `Cannot select this space. It is already included in package "${coveredByPackage.packageTitle}". Please unselect the package first if you want this space.`
          );
          return;
        }
      }
      
      // Check 2: If adding a PACKAGE, check if any of its spaces are already selected
      if (isPackage && packageSpaceIds.length > 0) {
        const alreadySelectedSpaces = state.selectedItems.filter((sel) => 
          packageSpaceIds.includes(Number(sel.id))
        );
        if (alreadySelectedSpaces.length > 0) {
          const spaceNames = alreadySelectedSpaces.map((s) => s.title).join(", ");
          console.warn(
            `Cannot select this package. The following spaces are already selected: ${spaceNames}.`,
          );
          alert(
            `Cannot select this package. The following spaces are already selected: ${spaceNames}. Please unselect the space(s) first if you want this package.`
          );
          return;
        }
        
        // Check 3: Check for overlapping packages (packages that share any space)
        const otherPackages = state.selectedItems.filter((sel) => {
          if (sel.type !== "package") return false;
          const otherPkg = sel as Package;
          return "space_ids" in otherPkg && 
            Array.isArray(otherPkg.space_ids) && 
            otherPkg.space_ids.some((sid) => packageSpaceIds.includes(sid));
        });
        if (otherPackages.length > 0) {
          const pkgNames = otherPackages.map((p) => p.title).join(", ");
          console.warn(
            `This package overlaps with already selected package: ${pkgNames}.`,
          );
          alert(
            `This package overlaps with an already selected package: ${pkgNames}. Please unselect the existing package first.`
          );
          return;
        }
      }
      
      // Check 4: Physical resource overlap check (existing)
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
      
      // Add the item
      const typedItem: SelectionItem = (
        isPackage
          ? { ...item, type: "package" as const }
          : { ...item, type: "space" as const }
      ) as SelectionItem;
      const updatedItems = [...state.selectedItems, typedItem];
      
      // Track package coverage
      let newPackageCoverage = state.packageCoverage;
      if (isPackage && packageSpaceIds.length > 0) {
        newPackageCoverage = [
          ...state.packageCoverage,
          {
            packageId: targetId,
            packageTitle: itemTitle,
            coveredSpaceIds: packageSpaceIds,
          },
        ];
        console.log("📦 Added packageCoverage:", newPackageCoverage);
      }

      const newLocked = computeLockedResourceIds(updatedItems, map);
      set({ 
        selectedItems: updatedItems, 
        lockedResourceIds: newLocked,
        packageCoverage: newPackageCoverage 
      });

      console.log(`Selected: ${targetId}. Updating locks...`);
    }
  },
clearItems: () =>
    set({
      currentStep: 1,
      selectedItems: [],
      lockedResourceIds: [],
      packageCoverage: [],
      selectedDate: "",
      selectedSlotWindows: [],
      selectedStartTime: "",
      selectedEndTime: "",
      availableExtras: [],
      selectedExtras: [],
      packageQuestionAnswers: {},
      checkoutUrl: null,
      bookingId: null,
      totalPrice: 0,
      priceBreakdown: [],
      extrasDetails: [],
    }),
  getPrimarySpaceId: () => {
    const state = get();
    if (state.selectedItems.length === 0) return null;
    const item = state.selectedItems[0];
    if (item.type === "space") {
      return Number(item.id);
    }
    if (item.type === "package") {
      const pkg = item as Package;
      if ("space_id" in pkg && pkg.space_id) {
        const resolvedId = Number(pkg.space_id);
        console.log("Package resolved to Space:", resolvedId);
        return resolvedId;
      }
    }
    return Number(item.id); // Fallback
  },
  getAllSpaceIds: () => {
    const state = get();
    return state.selectedItems
      .filter(item => item.type === "space")
      .map(item => Number(item.id));
  },
  getAllPackageIds: () => {
    const state = get();
    return state.selectedItems
      .filter(item => item.type === "package")
      .map(item => Number(item.id));
  },
  getCoveredSpaceIds: () => {
    const state = get();
    return state.packageCoverage.flatMap((pc) => pc.coveredSpaceIds);
  },

  // NEW: Merged extras selector - computes included/paid split for UI
  getMergedExtras: (): MergedExtra[] => {
    const state = get();
    const { selectedExtras, availableExtras } = state;
    
    if (selectedExtras.length === 0) return [];
    
    const includedQtyMap = getIncludedExtraQtyMap(state.selectedItems);
    
    // Build merged extras array
    const merged: MergedExtra[] = [];
    const extraMap = new Map(availableExtras.map((e) => [e.id, e]));
    
    for (const sel of selectedExtras) {
      const extraInfo = extraMap.get(sel.extra_id);
      const total_qty = sel.quantity;
      const included_qty = includedQtyMap.get(sel.extra_id) ?? (sel.included ? 1 : 0);
      const paid_qty = Math.max(0, total_qty - included_qty);
      
      merged.push({
        extra_id: sel.extra_id,
        title: extraInfo?.title ?? `Extra ${sel.extra_id}`,
        total_qty,
        included_qty,
        paid_qty,
        unit_price: extraInfo?.price ?? 0,
        is_locked: total_qty <= included_qty,
      });
    }
    
    return merged;
  },
  getLockedResourceIds: () => {
    const state = get();
    console.log("getLockedResourceIds CALLED");
    console.log(
      "  selectedItems:",
      state.selectedItems.map((i) => i.id),
    );
    if (!state.resourceMap) {
      console.log("  NO resourceMap, returning []");
      return [];
    }
    const result = computeLockedResourceIds(state.selectedItems, state.resourceMap);
    console.log("  FINAL lockedResourceIds:", result);
    return result;
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
      selectedSlotWindows: [],
      selectedStartTime: "",
      selectedEndTime: "",
      selectedExtras: [],
    }),
  setSelectedSlotWindows: (slots: SelectedSlotWindow[]) => {
    const span = getSelectedSlotSpan(slots);
    set({
      selectedSlotWindows: slots,
      selectedStartTime: span?.startTime ?? "",
      selectedEndTime: span?.endTime ?? "",
      selectedExtras: [],
    });
  },
  setStartTime: (time: string) =>
    set({
      selectedSlotWindows: [],
      selectedStartTime: time,
      selectedEndTime: "",
      selectedExtras: [],
    }),
  setEndTime: (time: string) => set({ selectedEndTime: time }),

  // ── Step 3 ───────────────────────────────────────────────────────────────
  setAvailableExtras: (extras: Extra[]) => {
    console.log("📦 STORE setAvailableExtras:", extras.length, "extras");
    set({ availableExtras: extras });
  },

  setSelectedExtras: (extras: SelectedExtra[]) => {
    console.log("📦 STORE setSelectedExtras:", extras.length, "extras");
    set({ selectedExtras: extras });
  },

  toggleExtra: (extra_id: number, quantity: number = 1, included: boolean = false) => {
    const current = get().selectedExtras;
    console.group("🔄 STORE toggleExtra");
    console.log(
      "Before - extra_id:",
      extra_id,
      "current selectedExtras:",
      current.map((e) => e.extra_id),
    );
    const exists = current.find((e) => e.extra_id === extra_id);

    if (exists) {
      // If included, cannot remove completely - just reduce quantity
      if (exists.included) {
        // If trying to remove included extra, reduce to minimum (included qty only)
        const newExtras = current.map((e) =>
          e.extra_id === extra_id ? { ...e, quantity: 1, included: true } : e
        );
        console.log(
          "REDUCE TO INCLUDED - new selectedExtras:",
          newExtras.map((e) => e.extra_id),
        );
        set({ selectedExtras: newExtras });
      } else {
        // Remove completely (non-included)
        const newExtras = current.filter((e) => e.extra_id !== extra_id);
        console.log(
          "REMOVE - new selectedExtras:",
          newExtras.map((e) => e.extra_id),
        );
        set({ selectedExtras: newExtras });
      }
    } else {
      // Add (new extra or re-add included)
      const newExtras = [...current, { extra_id, quantity, included }];
      console.log(
        "ADD - new selectedExtras:",
        newExtras.map((e) => e.extra_id),
      );
      set({ selectedExtras: newExtras });
    }
    console.groupEnd();
  },

  removeReviewExtra: (extra_id: number) =>
    set((state) => {
      const existing = state.selectedExtras.find((extra) => extra.extra_id === extra_id);
      if (!existing) return {};

      const includedQty = getIncludedExtraQtyMap(state.selectedItems).get(extra_id) ?? 0;
      const selectedExtras =
        includedQty > 0
          ? existing.quantity <= includedQty
            ? state.selectedExtras
            : state.selectedExtras.map((extra) =>
                extra.extra_id === extra_id
                  ? { ...extra, quantity: includedQty, included: true }
                  : extra,
              )
          : state.selectedExtras.filter((extra) => extra.extra_id !== extra_id);

      if (selectedExtras === state.selectedExtras) {
        return {};
      }

      return {
        selectedExtras,
        checkoutUrl: null,
        bookingId: null,
        totalPrice: 0,
        priceBreakdown: [],
        extrasDetails: [],
      };
    }),

  // Increment extra quantity by 1
  incrementExtra: (extra_id: number) => {
    const current = get().selectedExtras;
    const exists = current.find((e) => e.extra_id === extra_id);
    if (exists) {
      const newExtras = current.map((e) =>
        e.extra_id === extra_id ? { ...e, quantity: e.quantity + 1 } : e
      );
      set({ selectedExtras: newExtras });
    } else {
      // Add new with quantity 1
      set({ selectedExtras: [...current, { extra_id, quantity: 1, included: false }] });
    }
  },

  // Decrement extra quantity by 1 (respects included_qty minimum)
  decrementExtra: (extra_id: number) => {
    const current = get().selectedExtras;
    const exists = current.find((e) => e.extra_id === extra_id);
    if (!exists) return;

    const includedQty = getIncludedExtraQtyMap(get().selectedItems).get(extra_id) ?? 0;

    // Can't go below included_qty
    if (exists.quantity <= includedQty) {
      // If it's included, reduce to included_qty (which is 1), otherwise stay at minimum
      if (exists.included) {
        const newExtras = current.map((e) =>
          e.extra_id === extra_id ? { ...e, quantity: includedQty || 1, included: true } : e
        );
        set({ selectedExtras: newExtras });
      }
      return;
    }

    // If quantity would go to 0, remove entirely
    if (exists.quantity === 1) {
      const newExtras = current.filter((e) => e.extra_id !== extra_id);
      set({ selectedExtras: newExtras });
    } else {
      const newExtras = current.map((e) =>
        e.extra_id === extra_id ? { ...e, quantity: e.quantity - 1 } : e
      );
      set({ selectedExtras: newExtras });
    }
  },

  // Set included extras from package (auto-added)
  setIncludedExtras: (extraIds: number[]) => {
    const current = get().selectedExtras;
    console.log("🔄 setIncludedExtras:", extraIds);

    // Build new extras array, updating existing entries or adding new ones
    const newExtrasMap = new Map<number, SelectedExtra>();

    // First, add all current extras
    for (const e of current) {
      newExtrasMap.set(e.extra_id, e);
    }

    // Then, update/add included extras
    for (const extraId of extraIds) {
      const exists = newExtrasMap.get(extraId);
      if (!exists) {
        // New extra - add as included
        newExtrasMap.set(extraId, { extra_id: extraId, quantity: 1, included: true });
      } else if (!exists.included) {
        // Update existing non-included to included (keep higher quantity if any)
        newExtrasMap.set(extraId, { ...exists, included: true });
      }
      // If already included, do nothing (keep existing)
    }

    set({ selectedExtras: Array.from(newExtrasMap.values()) });
  },

  // ── Step 4 ───────────────────────────────────────────────────────────────
  setCustomerField: (key: string, value: CustomerValue) =>
    set((state) => ({
      customerInfo: { ...state.customerInfo, [key]: value },
    })),
  setPackageQuestionAnswer: (
    answerKey: string,
    value: string | number | string[],
    othersText?: string,
  ) =>
    set((state) => ({
      packageQuestionAnswers: {
        ...state.packageQuestionAnswers,
        [answerKey]: {
          value,
          ...(othersText !== undefined ? { others_text: othersText } : {}),
        },
      },
    })),
  clearPackageQuestionAnswers: () => set({ packageQuestionAnswers: {} }),
  hasPackageQuestionsStep: () => {
    const state = get();
    return state.selectedItems.some((item) => {
      if (item.type !== "package") return false;
      return Array.isArray((item as Package).theme_meta_fields) && (item as Package).theme_meta_fields!.length > 0;
    });
  },
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
  }) => {
    set({ checkoutUrl, bookingId, totalPrice, priceBreakdown: breakdown });
  },

  setPriceBreakdown: (breakdown: PriceBreakdownItem[], total: number, extrasDetails: import("@/types").ExtraDetail[] = []) => {
    console.group("💰 STORE setPriceBreakdown");
    console.log("Breakdown:", breakdown);
    console.log("Total:", total);
    console.log("ExtrasDetails:", extrasDetails);
    console.groupEnd();
    // Backend provides detailed labels, no enrichment needed
    set({ priceBreakdown: breakdown, totalPrice: total, extrasDetails });
  },

  // ── Step 6 ───────────────────────────────────────────────────────────────
  confirmBooking: () => {
    clearStoredDraft();
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
        clearStoredDraft();
        get().reset();
        set({ hasCartBooking: true });
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
      clearStoredDraft();
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

  hydrateDraft: () => {
    if (!isBrowser()) return false;

    const draft = parseDraft(
      window.localStorage.getItem(getBookingDraftStorageKey()),
    );

    if (!draft) {
      clearStoredDraft();
      return false;
    }

    const resourceMap = get().resourceMap;
    const selectedItems = draft.selectedItems;

    set({
      currentStep: getRestorableStep(draft),
      selectedItems,
      lockedResourceIds: computeLockedResourceIds(selectedItems, resourceMap),
      packageCoverage: buildPackageCoverage(selectedItems),
      selectedDate: draft.selectedDate,
      selectedSlotWindows: draft.selectedSlotWindows,
      selectedStartTime: draft.selectedStartTime,
      selectedEndTime: draft.selectedEndTime,
      availableExtras: [],
      selectedExtras: draft.selectedExtras,
      customerInfo: { ...DEFAULT_CUSTOMER, ...draft.customerInfo },
      packageQuestionAnswers: draft.packageQuestionAnswers,
      checkoutUrl: null,
      bookingId: null,
      bookingStatus: "pending",
      totalPrice: 0,
      priceBreakdown: [],
      extrasDetails: [],
      isConfirmed: false,
      hasCartBooking: false,
    });

    return true;
  },

  clearDraft: () => {
    clearStoredDraft();
  },

  reset: () => {
    set(createInitialBookingState());
  },
}));

useBookingStore.subscribe((state) => {
  persistDraft(state);
});
