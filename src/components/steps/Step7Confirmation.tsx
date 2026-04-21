import React, { useEffect, useState } from "react";
import { useBookingStore } from "@/store/bookingStore";

export function Step7Confirmation() {
  const bookingStatus = useBookingStore((s) => s.bookingStatus);
  const {
    customerInfo,
    selectedSpace,
    selectedPackage,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    totalPrice,
    selectedExtras,
    availableExtras,
    bookingId,
    reset,
  } = useBookingStore();

  const spaceName =
    selectedSpace?.title ?? selectedPackage?.space_name ?? "Space";

  const getExtraTitle = (extraId: number) =>
    availableExtras.find((e) => e.id === extraId)?.title ?? `Extra #${extraId}`;

  return (
    <div className="sb-step sb-step-7">
      {/* Success banner */}
      <div className="sb-confirm-banner">
        <span className="sb-confirm-icon" aria-hidden="true">
          ✅
        </span>
        <h2 className="sb-confirm-title">
          {bookingStatus === "confirmed"
            ? "Payment Confirmed!"
            : "Booking Created!"}
        </h2>
        <p className="sb-confirm-subtitle">
          {bookingStatus === "confirmed"
            ? "Payment successful! A human will review this booking to ensure total accuracy. You can expect a confirmation update from a member of our staff within 24 hours."
            : "You're being redirected to secure payment. A confirmation email will be sent after payment."}
        </p>
      </div>

      {/* Invoice card */}
      <div className="sb-invoice">
        <div className="sb-invoice__header">
          <h3>
            Booking Receipt (
            {bookingStatus === "confirmed"
              ? "Paid - Under Review"
              : "Pending Payment"}
            )
          </h3>
          {bookingId && <span className="sb-invoice__id">#{bookingId}</span>}
        </div>

        <table className="sb-invoice__table">
          <tbody>
            <tr>
              <th>Space</th>
              <td>{spaceName}</td>
            </tr>
            <tr>
              <th>Date</th>
              <td>{selectedDate}</td>
            </tr>
            <tr>
              <th>Time</th>
              <td>
                {selectedStartTime} – {selectedEndTime}
              </td>
            </tr>
            <tr>
              <th>Name</th>
              <td>{customerInfo.name}</td>
            </tr>
            <tr>
              <th>Email</th>
              <td>{customerInfo.email}</td>
            </tr>
            {customerInfo.phone && (
              <tr>
                <th>Phone</th>
                <td>{customerInfo.phone}</td>
              </tr>
            )}
            {selectedExtras.length > 0 && (
              <tr>
                <th>Extras</th>
                <td>
                  <ul className="sb-confirm-extras">
                    {selectedExtras.map((e) => (
                      <li key={e.extra_id}>
                        {getExtraTitle(e.extra_id)}
                        {e.quantity > 1 && ` × ${e.quantity}`}
                      </li>
                    ))}
                  </ul>
                </td>
              </tr>
            )}
            <tr className="sb-invoice__total">
              <th>
                {bookingStatus === "confirmed" ? "Total Paid" : "Total Due"}
              </th>
              <td>
                {totalPrice.toFixed(2)} {window.sbConfig.symbol}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      {/* Actions */}
      <div className="sb-confirm-actions">
        <button className="sb-btn sb-btn--ghost" onClick={() => window.print()}>
          🖨 Print Receipt
        </button>
        <button className="sb-btn sb-btn--primary" onClick={reset}>
          Make Another Booking
        </button>
      </div>

      {bookingStatus === "confirmed" && (
        <div
          className="sb-review-notice"
          style={{
            background: "#d4edda",
            padding: "15px",
            borderRadius: "8px",
            margin: "20px 0",
            borderLeft: "4px solid #28a745",
          }}
        >
          <strong>📋 Next Steps:</strong> Our team will review your booking
          within 24 hours and send final confirmation.
        </div>
      )}
      <p className="sb-confirm-lookup">
        {bookingStatus === "confirmed"
          ? "Booking confirmed and under review. Manage via "
          : "You'll receive confirmation after payment. Manage bookings via "}
        <a href="/booking-lookup/">booking lookup</a> with your email.
      </p>
    </div>
  );
}
