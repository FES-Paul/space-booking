import React, { useEffect, useState } from "react";
import { useBookingStore } from "@/store/bookingStore";
import { fetchExtras, fetchPricing } from "@/utils/api";
import type { Extra } from "@/types";

export function Step3Addons() {
  const {
    selectedSpace,
    selectedPackage,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    selectedExtras,
    availableExtras,
    toggleExtra,
    setAvailableExtras,
    setPriceBreakdown,
    nextStep,
    prevStep,
  } = useBookingStore();

  const [extras, setExtras] = useState<Extra[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [preview, setPreview] = useState<{
    total: number;
    breakdown: { label: string; amount: number }[];
  } | null>(null);

  const spaceId = selectedSpace?.id ?? selectedPackage?.space_id ?? 0;
  const packageId = selectedPackage?.id;

  useEffect(() => {
    if (!spaceId || !selectedDate || !selectedStartTime || !selectedEndTime)
      return;
    setLoading(true);
    fetchExtras(spaceId, selectedDate, selectedStartTime, selectedEndTime)
      .then((data) => {
        setExtras(data);
        setAvailableExtras(data);
      })
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, [
    spaceId,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    setAvailableExtras,
  ]);

  // Re-calculate price whenever extras selection changes
  useEffect(() => {
    if (!spaceId || !selectedDate || !selectedStartTime || !selectedEndTime)
      return;
    fetchPricing({
      space_id: spaceId,
      date: selectedDate,
      start_time: selectedStartTime,
      end_time: selectedEndTime,
      extras: selectedExtras,
      package_id: packageId,
    })
      .then((res) => {
        console.log("RESPONSE BREAKDOWN: ", res);
        const enrichedBreakdown = res.breakdown.map((item: any) => {
          let label = item.label;
          if (label === "Package price" && selectedPackage) {
            label = `${selectedPackage.title}`;
          } else if (label === "Extras" && selectedExtras.length > 0) {
            const extraNames = selectedExtras
              .map((e: any) => {
                const extra = availableExtras.find(
                  (ex: any) => ex.id === e.extra_id,
                );
                return extra ? extra.title : `Extra #${e.extra_id}`;
              })
              .join(" + ");
            label = `Extras: ${extraNames}`;
          } else if (
            selectedSpace &&
            (label.includes("–") || label.match(/^\\d{2}:\\d{2}–\\d{2}:\\d{2}/))
          ) {
            label = `${selectedSpace.title}: ${label}`;
          }
          return { ...item, label };
        });
        setPreview({ total: res.total_price, breakdown: enrichedBreakdown });
        setPriceBreakdown(enrichedBreakdown, res.total_price);
      })
      .catch(() => {
        /* silent */
      });
  }, [
    selectedExtras,
    availableExtras,
    selectedSpace,
    selectedPackage,
    spaceId,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    packageId,
  ]);

  const isSelected = (id: number) =>
    selectedExtras.some((e) => e.extra_id === id);

  return (
    <div className="sb-step sb-step-3">
      <h2 className="sb-step__title">Add-ons & Extras</h2>

      {loading && <div className="sb-loading">Loading extras…</div>}
      {error && <div className="sb-error">{error}</div>}

      {!loading && extras.length === 0 && (
        <p className="sb-empty">No extras available for this time slot.</p>
      )}

      {!loading && extras.length > 0 && (
        <div className="sb-extras">
          {extras.map((extra) => (
            <div
              key={extra.id}
              className={`sb-extra-card ${isSelected(extra.id) ? "sb-extra-card--selected" : ""} ${!extra.is_available ? "sb-extra-card--unavailable" : ""}`}
            >
              <div className="sb-extra-card__info">
                {extra.thumbnail && (
                  <img
                    src={extra.thumbnail}
                    alt={extra.title}
                    className="sb-extra-card__img"
                  />
                )}
                <div>
                  <strong className="sb-extra-card__name">{extra.title}</strong>
                  <p className="sb-extra-card__desc">{extra.description}</p>
                  <span className="sb-extra-card__price">
                    {window.sbConfig.symbol}
                    {extra.price.toFixed(2)}
                  </span>
                  {!extra.is_available && extra.unavailable_reason && (
                    <span
                      className={`sb-badge sb-badge--sold-out ${
                        extra.unavailable_reason === "space_override"
                          ? "sb-badge--closed"
                          : ""
                      }`}
                    >
                      {extra.unavailable_reason === "space_override"
                        ? "Closed this time"
                        : "Sold Out"}
                    </span>
                  )}
                  {extra.is_available && extra.available_qty < 3 && (
                    <span className="sb-badge sb-badge--low">
                      Only {extra.available_qty} left
                    </span>
                  )}
                </div>
              </div>
              <button
                className={`sb-btn ${isSelected(extra.id) ? "sb-btn--danger" : "sb-btn--secondary"}`}
                disabled={!extra.is_available}
                onClick={() => toggleExtra(extra.id)}
              >
                {isSelected(extra.id) ? "Remove" : "Add"}
              </button>
            </div>
          ))}
        </div>
      )}

      {/* Live price preview */}
      {preview && (
        <div className="sb-price-preview">
          <h4>Price Preview</h4>
          <ul className="sb-breakdown">
            {preview.breakdown.map((item, i) => (
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
              {preview.total.toFixed(2)}
            </strong>
          </div>
        </div>
      )}

      <div className="sb-step__actions">
        <button className="sb-btn sb-btn--ghost" onClick={prevStep}>
          ← Back
        </button>
        <button className="sb-btn sb-btn--primary" onClick={nextStep}>
          Continue →
        </button>
      </div>
    </div>
  );
}
