import { describe, expect, it } from "vitest";
import type { TimeSlot } from "@/types";
import {
  addMinutesToTime,
  getSelectedSlotSpan,
  updateContiguousSlotSelection,
} from "@/utils/slotSelection";

const fixedSlots: TimeSlot[] = [
  {
    slot_id: "slot_1",
    start: "10:00",
    end: "12:00",
    available: true,
  },
  {
    slot_id: "slot_2",
    start: "13:00",
    end: "15:00",
    available: true,
  },
  {
    slot_id: "slot_3",
    start: "16:00",
    end: "18:00",
    available: true,
  },
];

describe("slotSelection", () => {
  it("extends the selected group only when the clicked slot is adjacent", () => {
    const selection = updateContiguousSlotSelection(
      fixedSlots,
      [
        { slotId: "slot_1", start: "10:00", end: "12:00" },
        { slotId: "slot_2", start: "13:00", end: "15:00" },
      ],
      fixedSlots[2],
    );

    expect(selection).toEqual([
      { slotId: "slot_1", start: "10:00", end: "12:00" },
      { slotId: "slot_2", start: "13:00", end: "15:00" },
      { slotId: "slot_3", start: "16:00", end: "18:00" },
    ]);
  });

  it("starts a new group when the clicked slot is not adjacent", () => {
    const selection = updateContiguousSlotSelection(
      fixedSlots,
      [{ slotId: "slot_1", start: "10:00", end: "12:00" }],
      fixedSlots[2],
    );

    expect(selection).toEqual([
      { slotId: "slot_3", start: "16:00", end: "18:00" },
    ]);
  });

  it("does not group across a booked slot interruption", () => {
    const interruptedSlots = [
      fixedSlots[0],
      [
        { ...fixedSlots[1], available: false },
        fixedSlots[2],
      ],
    ].flat();
    const selection = updateContiguousSlotSelection(
      interruptedSlots,
      [{ slotId: "slot_1", start: "10:00", end: "12:00" }],
      interruptedSlots[2],
    );

    expect(selection).toEqual([
      { slotId: "slot_3", start: "16:00", end: "18:00" },
    ]);
  });

  it("shrinks the group from the clicked edge slot", () => {
    const selection = updateContiguousSlotSelection(
      fixedSlots,
      [
        { slotId: "slot_1", start: "10:00", end: "12:00" },
        { slotId: "slot_2", start: "13:00", end: "15:00" },
        { slotId: "slot_3", start: "16:00", end: "18:00" },
      ],
      fixedSlots[2],
    );

    expect(selection).toEqual([
      { slotId: "slot_1", start: "10:00", end: "12:00" },
      { slotId: "slot_2", start: "13:00", end: "15:00" },
    ]);
  });

  it("derives the total duration from the earliest slot start to the last slot end", () => {
    const span = getSelectedSlotSpan([
      { slotId: "slot_1", start: "10:00", end: "12:00" },
      { slotId: "slot_2", start: "13:00", end: "15:00" },
    ]);

    expect(span).toMatchObject({
      startTime: "10:00",
      endTime: "15:00",
      totalMinutes: 300,
      durationHours: 5,
      slotMinutes: 240,
      gapMinutes: 60,
    });
  });

  it("adds minutes to a time string without changing the format", () => {
    expect(addMinutesToTime("11:30", 30)).toBe("12:00");
    expect(addMinutesToTime("23:45", 30)).toBe("00:15");
  });
});
