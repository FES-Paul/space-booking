import type { SelectedSlotWindow, TimeSlot } from "@/types";

export interface SlotSelectionSpan {
  startTime: string;
  endTime: string;
  totalMinutes: number;
  durationHours: number;
  slotMinutes: number;
  gapMinutes: number;
}

export const timeToMinutes = (time: string): number => {
  const [hours, minutes] = time.split(":").map(Number);
  return hours * 60 + minutes;
};

export const getTimeSlotKey = (
  slot: Pick<TimeSlot, "start" | "end" | "slot_id">,
): string => slot.slot_id ?? `${slot.start}-${slot.end}`;

export const toSelectedSlotWindow = (slot: TimeSlot): SelectedSlotWindow => ({
  slotId: getTimeSlotKey(slot),
  start: slot.start,
  end: slot.end,
});

export const isSelectedSlotWindowMatch = (
  selectedSlot: SelectedSlotWindow,
  slot: Pick<TimeSlot, "start" | "end" | "slot_id">,
): boolean =>
  selectedSlot.slotId === getTimeSlotKey(slot) &&
  selectedSlot.start === slot.start &&
  selectedSlot.end === slot.end;

const getSelectedSlotIndexes = (
  slots: TimeSlot[],
  selectedSlots: SelectedSlotWindow[],
): number[] =>
  selectedSlots
    .map((selectedSlot) =>
      slots.findIndex((slot) => isSelectedSlotWindowMatch(selectedSlot, slot)),
    )
    .filter((index) => index >= 0)
    .sort((left, right) => left - right);

const buildSelectionFromIndexes = (
  slots: TimeSlot[],
  startIndex: number,
  endIndex: number,
): SelectedSlotWindow[] => slots.slice(startIndex, endIndex + 1).map(toSelectedSlotWindow);

export const updateContiguousSlotSelection = (
  slots: TimeSlot[],
  selectedSlots: SelectedSlotWindow[],
  targetSlot: TimeSlot,
): SelectedSlotWindow[] => {
  if (!targetSlot.available) {
    return selectedSlots;
  }

  const targetIndex = slots.findIndex(
    (slot) => getTimeSlotKey(slot) === getTimeSlotKey(targetSlot),
  );
  if (targetIndex < 0) {
    return selectedSlots;
  }

  if (selectedSlots.length === 0) {
    return [toSelectedSlotWindow(targetSlot)];
  }

  const selectedIndexes = getSelectedSlotIndexes(slots, selectedSlots);
  if (selectedIndexes.length === 0) {
    return [toSelectedSlotWindow(targetSlot)];
  }

  const startIndex = selectedIndexes[0];
  const endIndex = selectedIndexes[selectedIndexes.length - 1];
  const isInsideSelection =
    targetIndex >= startIndex && targetIndex <= endIndex &&
    selectedIndexes.includes(targetIndex);

  if (isInsideSelection) {
    if (startIndex === endIndex) {
      return [];
    }

    if (targetIndex === startIndex) {
      return buildSelectionFromIndexes(slots, startIndex + 1, endIndex);
    }

    if (targetIndex === endIndex) {
      return buildSelectionFromIndexes(slots, startIndex, endIndex - 1);
    }

    return [toSelectedSlotWindow(targetSlot)];
  }

  if (targetIndex === startIndex - 1) {
    return buildSelectionFromIndexes(slots, targetIndex, endIndex);
  }

  if (targetIndex === endIndex + 1) {
    return buildSelectionFromIndexes(slots, startIndex, targetIndex);
  }

  return [toSelectedSlotWindow(targetSlot)];
};

export const getSelectedSlotSpan = (
  selectedSlots: SelectedSlotWindow[],
): SlotSelectionSpan | null => {
  if (selectedSlots.length === 0) {
    return null;
  }

  const orderedSlots = [...selectedSlots].sort(
    (left, right) => timeToMinutes(left.start) - timeToMinutes(right.start),
  );
  const slotMinutes = orderedSlots.reduce(
    (total, slot) => total + (timeToMinutes(slot.end) - timeToMinutes(slot.start)),
    0,
  );
  const startTime = orderedSlots[0].start;
  const endTime = orderedSlots[orderedSlots.length - 1].end;
  const totalMinutes = timeToMinutes(endTime) - timeToMinutes(startTime);
  const gapMinutes = Math.max(0, totalMinutes - slotMinutes);

  return {
    startTime,
    endTime,
    totalMinutes,
    durationHours: Math.round((totalMinutes / 60) * 100) / 100,
    slotMinutes,
    gapMinutes,
  };
};
