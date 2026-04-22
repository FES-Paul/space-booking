import React, { useEffect, useState } from "react";
import { useBookingStore } from "@/store/bookingStore";
import { fetchAvailability } from "@/utils/api";
import type { TimeSlot } from "@/types";

export function Step2Scheduling() {
  const {
    selectedSpace,
    selectedPackage,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    setDate,
    setStartTime,
    setEndTime,
    nextStep,
    prevStep,
  } = useBookingStore();

  const spaceId = selectedSpace?.id ?? selectedPackage?.space_id;

  const [slots, setSlots] = useState<TimeSlot[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const timeToMinutes = (timeStr: string): number => {
    const [h, m] = timeStr.split(":").map(Number);
    return h * 60 + m;
  };

  const formatTimeTo12Hour = (timeStr: string): string => {
    const [hourStr, minuteStr] = timeStr.split(":");
    let hour = parseInt(hourStr, 10);
    const minutes = minuteStr;
    const period = hour >= 12 ? "PM" : "AM";
    hour = hour % 12;
    if (hour === 0) hour = 12;
    return `${hour}:${minutes} ${period}`;
  };

  const minDuration = selectedSpace?.min_duration ?? 1;
  const maxDuration = selectedSpace?.max_duration ?? 8;

  const isStartValid = (slotIndex: number): boolean => {
    if (slotIndex + minDuration > slots.length) return false;
    for (let k = 0; k < minDuration; k++) {
      if (!slots[slotIndex + k].available) return false;
    }
    return true;
  };

  // Auto-set first valid end time (>= minDuration) when start changes
  useEffect(() => {
    const firstValidEnd = getFirstValidEnd();
    if (firstValidEnd) {
      setEndTime(firstValidEnd);
    }
  }, [selectedStartTime, slots, minDuration]);

  // Minimum selectable date = today
  const today = new Date().toISOString().split("T")[0];

  useEffect(() => {
    if (!selectedDate || !spaceId) return;
    setLoading(true);
    setError("");
    fetchAvailability(spaceId, selectedDate)
      .then((res) => setSlots(res.slots))
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, [selectedDate, spaceId]);

  // Sequential available end slots starting from minDuration (excluding default)
  const endTimeOptions: TimeSlot[] = [];
  const startIndex = slots.findIndex((s) => s.start === selectedStartTime);
  if (startIndex >= 0) {
    const minEndIndex = startIndex + minDuration;
    for (
      let j = minEndIndex + 1; // +1 to skip default minDuration slot
      j < slots.length && j < startIndex + maxDuration + 1;
      j++
    ) {
      if (!slots[j].available) break;
      endTimeOptions.push(slots[j]);
    }
  }

  const canProceed = selectedDate && selectedStartTime && selectedEndTime;

  // Compute first valid end slot based on minDuration
  const getFirstValidEnd = (): string => {
    const startIdx = slots.findIndex((s) => s.start === selectedStartTime);
    if (startIdx < 0 || startIdx + minDuration > slots.length) return "";
    const candidate = slots[startIdx + minDuration - 1];
    return candidate?.available ? candidate.end : "";
  };

  return (
    <div className="sb-step sb-step-2">
      <h2 className="sb-step__title">Pick Your Date & Time</h2>

      {/* Date picker */}
      <div className="sb-field">
        <label className="sb-label" htmlFor="sb-date">
          Date
        </label>
        <input
          id="sb-date"
          type="date"
          className="sb-input"
          min={today}
          value={selectedDate}
          onChange={(e) => setDate(e.target.value)}
        />
      </div>

      {loading && <div className="sb-loading">Checking availability…</div>}
      {error && <div className="sb-error">{error}</div>}

      {!loading && selectedDate && slots.length > 0 && (
        <>
          {/* Start time grid */}
          <div className="sb-field">
            <label className="sb-label">Start Time</label>
            <div className="sb-slot-grid">
              {slots.map((slot, i) => {
                // if (!slot.available) return null;
                const validStart = isStartValid(i);
                return (
                  <button
                    key={slot.start}
                    className={`sb-slot ${!validStart || !slot.available ? "sb-slot--invalid" : ""} ${selectedStartTime === slot.start ? "sb-slot--selected" : ""}`}
                    onClick={
                      validStart && slot.available
                        ? () => setStartTime(slot.start)
                        : undefined
                    }
                  >
                    {formatTimeTo12Hour(slot.start)}
                  </button>
                );
              })}
            </div>
          </div>

          {/* End time */}
          {selectedStartTime && (
            <div className="sb-field">
              <label className="sb-label" htmlFor="sb-end-time">
                End Time
              </label>
              <select
                id="sb-end-time"
                className="sb-input"
                value={selectedEndTime}
                onChange={(e) => setEndTime(e.target.value)}
              >
                {getFirstValidEnd() && (
                  <option key="default" value={getFirstValidEnd()}>
                    {minDuration}h ({formatTimeTo12Hour(getFirstValidEnd())})
                  </option>
                )}
                {endTimeOptions.map((slot, i) => {
                  const hours = Math.round(
                    (timeToMinutes(slot.end) -
                      timeToMinutes(selectedStartTime)) /
                      60,
                  );
                  const tooShort = hours < minDuration;
                  const tooLong = hours > maxDuration;
                  return (
                    <option
                      key={slot.end}
                      value={slot.end}
                      disabled={tooShort || tooLong}
                    >
                      {hours}h ({formatTimeTo12Hour(slot.end)})
                    </option>
                  );
                })}
              </select>
            </div>
          )}
        </>
      )}

      {!loading && selectedDate && slots.length === 0 && (
        <p className="sb-empty">
          No availability for this date. Please choose another day.
        </p>
      )}

      <div className="sb-step__actions">
        <button className="sb-btn sb-btn--ghost" onClick={prevStep}>
          ← Back
        </button>
        <button
          className="sb-btn sb-btn--primary"
          disabled={!canProceed}
          onClick={nextStep}
        >
          Continue →
        </button>
      </div>
    </div>
  );
}
