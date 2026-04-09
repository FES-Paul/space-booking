import React from 'react';
import { useBookingStore } from '@/store/bookingStore';

export function Step4Details() {
  const { customerInfo, setCustomerInfo, nextStep, prevStep } = useBookingStore();

  const { name, email, phone, notes } = customerInfo;

  const isValid =
    name.trim().length >= 2 &&
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isValid) nextStep();
  };

  return (
    <div className="sb-step sb-step-4">
      <h2 className="sb-step__title">Your Details</h2>

      <form className="sb-form" onSubmit={handleSubmit} noValidate>
        {/* Name */}
        <div className="sb-field">
          <label className="sb-label" htmlFor="sb-name">
            Full Name <span className="sb-required">*</span>
          </label>
          <input
            id="sb-name"
            type="text"
            className="sb-input"
            value={name}
            required
            minLength={2}
            autoComplete="name"
            onChange={(e) => setCustomerInfo({ name: e.target.value })}
          />
        </div>

        {/* Email */}
        <div className="sb-field">
          <label className="sb-label" htmlFor="sb-email">
            Email Address <span className="sb-required">*</span>
          </label>
          <input
            id="sb-email"
            type="email"
            className="sb-input"
            value={email}
            required
            autoComplete="email"
            onChange={(e) => setCustomerInfo({ email: e.target.value })}
          />
          <p className="sb-hint">We'll send your booking confirmation here.</p>
        </div>

        {/* Phone */}
        <div className="sb-field">
          <label className="sb-label" htmlFor="sb-phone">Phone (optional)</label>
          <input
            id="sb-phone"
            type="tel"
            className="sb-input"
            value={phone}
            autoComplete="tel"
            onChange={(e) => setCustomerInfo({ phone: e.target.value })}
          />
        </div>

        {/* Notes */}
        <div className="sb-field">
          <label className="sb-label" htmlFor="sb-notes">Special Requests (optional)</label>
          <textarea
            id="sb-notes"
            className="sb-input sb-textarea"
            rows={3}
            value={notes}
            onChange={(e) => setCustomerInfo({ notes: e.target.value })}
          />
        </div>

        <div className="sb-step__actions">
          <button type="button" className="sb-btn sb-btn--ghost" onClick={prevStep}>
            ← Back
          </button>
          <button type="submit" className="sb-btn sb-btn--primary" disabled={!isValid}>
            Review & Pay →
          </button>
        </div>
      </form>
    </div>
  );
}
