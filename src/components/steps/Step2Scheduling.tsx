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

  // Auto-set default 1h end time when start changes (start slot's own end)
  useEffect(() => {
    if (!selectedStartTime || slots.length === 0) return;

    const startSlot = slots.find((s) => s.start === selectedStartTime);
    if (startSlot) {
      setEndTime(startSlot.end);
    }
  }, [selectedStartTime]);

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

  // Sequential available post-start slots (buffer-trusting)
  const endTimeOptions: TimeSlot[] = [];
  const startIndex = slots.findIndex((s) => s.start === selectedStartTime);
  if (startIndex >= 0) {
    for (let j = startIndex + 1; j < slots.length && j < startIndex + 8; j++) {
      if (!slots[j].available) break;
      endTimeOptions.push(slots[j]);
    }
  }

  const canProceed = selectedDate && selectedStartTime && selectedEndTime;

  const minDuration = selectedSpace?.min_duration ?? 1;
  const maxDuration = selectedSpace?.max_duration ?? 8;

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
              {slots.map((slot) => (
                <button
                  key={slot.start}
                  disabled={!slot.available}
                  className={`sb-slot ${selectedStartTime === slot.start ? "sb-slot--selected" : ""} ${!slot.available ? "sb-slot--taken" : ""}`}
                  onClick={() => slot.available && setStartTime(slot.start)}
                >
                  {slot.start}
                </button>
              ))}
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
                <option
                  value={
                    slots.find((s) => s.start === selectedStartTime)?.end || ""
                  }
                >
                  1h (
                  {slots.find((s) => s.start === selectedStartTime)?.end ||
                    "..."}
                  )
                </option>
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
                      {hours}h ({slot.end})
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
