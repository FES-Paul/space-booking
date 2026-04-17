import React, { useState, useEffect } from "react";
import { useBookingStore } from "@/store/bookingStore";
import type { BookingStep } from "@/types";

export function Step5Terms() {
  const { nextStep, prevStep } = useBookingStore();
  const [bookingPolicy, setBookingPolicy] = useState("");
  const [agreed, setAgreed] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    // Load policy from global config
    if (window.sbConfig?.bookingPolicy && !bookingPolicy) {
      setBookingPolicy(window.sbConfig.bookingPolicy);
    }
  }, [bookingPolicy]);

  const handleNext = () => {
    if (!agreed) {
      setError("You must agree to the booking policy to continue.");
      return;
    }
    setError("");
    nextStep();
  };

  if (!bookingPolicy) {
    return (
      <div className="sb-step sb-step-5">
        <h2 className="sb-step__title">Terms & Agreement</h2>
        <div className="sb-error">
          Booking policy not configured. Please contact administrator.
        </div>
        <div className="sb-step__actions">
          <button className="sb-btn sb-btn--ghost" onClick={prevStep}>
            ← Back
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="sb-step sb-step-5">
      <h2 className="sb-step__title">Terms & Agreement</h2>

      <div className="sb-policy-container">
        <div
          className="sb-policy-text"
          dangerouslySetInnerHTML={{ __html: bookingPolicy }}
          style={{
            maxHeight: "400px",
            overflow: "auto",
            border: "1px solid #ddd",
            padding: "16px",
            borderRadius: "4px",
            background: "#f9f9f9",
            marginBottom: "16px",
          }}
        />

        <label className="sb-checkbox-label">
          <input
            type="checkbox"
            checked={agreed}
            onChange={(e) => setAgreed(e.target.checked)}
          />
          <span className="sb-checkbox-mark"></span>
          <span style={{fontSize: 14}}>I have read and agree to the booking policy above</span>
        </label>

        {error && (
          <div className="sb-error" style={{ marginTop: "8px" }}>
            {error}
          </div>
        )}
      </div>

      <div className="sb-step__actions">
        <button className="sb-btn sb-btn--ghost" onClick={prevStep}>
          ← Back
        </button>
        <button
          className="sb-btn sb-btn--primary"
          onClick={handleNext}
          disabled={!agreed}
        >
          Agree & Continue to Payment →
        </button>
      </div>
    </div>
  );
}
