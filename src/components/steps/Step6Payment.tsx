import React, { useState } from "react";
import { useBookingStore } from "@/store/bookingStore";
import { createBooking } from "@/utils/api";

export function Step6Payment() {
  const {
    checkoutUrl,
    priceBreakdown,
    totalPrice,
    selectedSpace,
    selectedPackage,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    customerInfo,
    selectedExtras,
    availableExtras,
    prevStep,
  } = useBookingStore();

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const handlePayment = async () => {
    const spaceId = selectedSpace?.id ?? selectedPackage?.space_id;
    const packageId = selectedPackage?.id;

    if (!spaceId) {
      setError("No space selected.");
      return;
    }

    setLoading(true);
    setError("");

    try {
      const res = await createBooking({
        space_id: spaceId,
        package_id: packageId,
        date: selectedDate,
        start_time: selectedStartTime,
        end_time: selectedEndTime,
        customer_name: customerInfo.name,
        customer_email: customerInfo.email,
        customer_phone: customerInfo.phone,
        notes: customerInfo.notes,
        extras: selectedExtras,
      });

      // Preserve enriched breakdown, update other checkout data
      useBookingStore.getState().setCheckoutData({
        checkoutUrl: res.checkout_url,
        bookingId: res.booking_id,
        totalPrice: res.total_price,
        breakdown: priceBreakdown, // Keep frontend enriched breakdown
      });

      // Redirect to WooCommerce checkout for payment
      window.location.href = res.checkout_url;
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="sb-step sb-step-6">
      <h2 className="sb-step__title">Complete Booking</h2>
      <div className="sb-checkout-summary">
        <h3>Final Review</h3>

        <div className="sb-summary-grid">
          <div className="sb-summary-row">
            <span>Space</span>
            <span>{selectedSpace?.title ?? selectedPackage?.space_name}</span>
          </div>
          <div className="sb-summary-row">
            <span>Date</span>
            <span>{selectedDate}</span>
          </div>
          <div className="sb-summary-row">
            <span>Time</span>
            <span>
              {selectedStartTime} – {selectedEndTime}
            </span>
          </div>
          <div className="sb-summary-row">
            <span>Name</span>
            <span>{customerInfo.name}</span>
          </div>
          <div className="sb-summary-row">
            <span>Email</span>
            <span>{customerInfo.email}</span>
          </div>
        </div>

        <h4>Price Breakdown</h4>
        <ul className="sb-breakdown">
          {priceBreakdown.map((item, i) => (
            <li key={i} className="sb-breakdown__item">
              <span>{item.label}</span>
              <span>
                {window.sbConfig.symbol}
                {item.amount.toFixed(2)}
              </span>
            </li>
          ))}
        </ul>
        <div className="sb-breakdown__total">
          Total:{" "}
          <strong>
            {window.sbConfig.symbol}
            {totalPrice.toFixed(2)}
          </strong>
        </div>

        {error && <div className="sb-error">{error}</div>}

        <div className="sb-step__actions">
          <button className="sb-btn sb-btn--ghost" onClick={prevStep}>
            ← Back
          </button>
          <button
            className="sb-btn sb-btn--primary"
            onClick={handlePayment}
            disabled={loading || !!checkoutUrl}
          >
            {loading
              ? "Creating Booking..."
              : checkoutUrl
                ? "Redirecting..."
                : "Proceed to Secure Payment →"}
          </button>
        </div>

        {checkoutUrl && (
          <p className="sb-note">
            Redirecting to secure WooCommerce checkout...
          </p>
        )}
      </div>
    </div>
  );
}
