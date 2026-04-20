import React, { useEffect, useState } from "react";
import { useBookingStore } from "@/store/bookingStore";
import { fetchSpaces, fetchPackages } from "@/utils/api";
import type { Space, Package } from "@/types";

export function Step1Selection() {
  const formatTimeTo12Hour = (timeStr: string): string => {
    const [hourStr, minuteStr] = timeStr.split(":");
    let hour = parseInt(hourStr, 10);
    const minutes = minuteStr;
    const period = hour >= 12 ? "PM" : "AM";
    hour = hour % 12;
    if (hour === 0) hour = 12;
    return `${hour}:${minutes} ${period}`;
  };

  const { selectedSpace, selectedPackage, setSpace, setPackage, nextStep } =
    useBookingStore();
  const [spaces, setSpaces] = useState<Space[]>([]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<"space" | "package">("space");

  useEffect(() => {
    setLoading(true);
    Promise.all([fetchSpaces(), fetchPackages()])
      .then(([s, p]) => {
        setSpaces(s);
        setPackages(p);
      })
      .finally(() => setLoading(false));
  }, []);

  const canProceed = selectedSpace !== null || selectedPackage !== null;

  return (
    <div className="sb-step sb-step-1">
      <h2 className="sb-step__title">Choose a Space or Package</h2>

      {/* Tabs */}
      <div className="sb-tabs">
        <button
          className={`sb-tab ${tab === "space" ? "sb-tab--active" : ""}`}
          onClick={() => setTab("space")}
        >
          Spaces
        </button>
        <button
          className={`sb-tab ${tab === "package" ? "sb-tab--active" : ""}`}
          onClick={() => setTab("package")}
        >
          Packages
        </button>
      </div>

      {loading && <div className="sb-loading">Loading…</div>}

      {/* Spaces */}
      {!loading && tab === "space" && (
        <div className="sb-cards">
          {spaces.map((space) => (
            <div
              key={space.id}
              className={`sb-card ${selectedSpace?.id === space.id ? "sb-card--selected" : ""}`}
              onClick={() => setSpace(space)}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => e.key === "Enter" && setSpace(space)}
            >
              <img
                src={
                  space.thumbnail ??
                  "https://upload.wikimedia.org/wikipedia/commons/1/14/No_Image_Available.jpg"
                }
                alt={space.title}
                className="sb-card__img"
              />
              <div className="sb-card__body">
                <h3 className="sb-card__title">{space.title}</h3>
                <p className="sb-card__excerpt">{space.excerpt}</p>
                <div className="sb-card__price">
                  <div>
                    Regular: {window.sbConfig.symbol}
                    {space.hourly_rate.toFixed(2)} / hr
                  </div>
                  <div>Min: {space.min_duration}h booking</div>
                  {space.capacity > 0 && (
                    <div>Up to {space.capacity} guests</div>
                  )}
                  {space.price_overrides &&
                    space.price_overrides.length > 0 && (
                      <div className="sb-price-overrides">
                        {space.price_overrides.map((ov, idx) => {
                          const todayDay = new Date().getDay();
                          const appliesToday = ov.days.includes(todayDay);
                          const dayNames = [
                            "Sun",
                            "Mon",
                            "Tue",
                            "Wed",
                            "Thu",
                            "Fri",
                            "Sat",
                          ];
                          const dayLabel = ov.days
                            .map((d) => dayNames[d])
                            .join(", ");
                          return (
                            <div key={idx} className="sb-override">
                              <span>
                                {dayLabel} {formatTimeTo12Hour(ov.start_time)}-
                                {formatTimeTo12Hour(ov.end_time)}:
                              </span>
                              <span>
                                {window.sbConfig.symbol}
                                {ov.hourly_rate.toFixed(2)}/hr
                              </span>
                              {appliesToday && (
                                <span className="sb-active-override">✓</span>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    )}
                </div>
              </div>
              {selectedSpace?.id === space.id && (
                <span className="sb-card__check" aria-label="Selected">
                  ✓
                </span>
              )}
            </div>
          ))}
          {spaces.length === 0 && (
            <p className="sb-empty">No spaces available at the moment.</p>
          )}
        </div>
      )}

      {/* Packages */}
      {!loading && tab === "package" && (
        <div className="sb-cards">
          {packages.map((pkg) => (
            <div
              key={pkg.id}
              className={`sb-card ${selectedPackage?.id === pkg.id ? "sb-card--selected" : ""}`}
              onClick={() => setPackage(pkg)}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => e.key === "Enter" && setPackage(pkg)}
            >
              <img
                src={
                  pkg.thumbnail ??
                  "https://upload.wikimedia.org/wikipedia/commons/1/14/No_Image_Available.jpg"
                }
                alt={pkg.title}
                className="sb-card__img"
              />
              <div className="sb-card__body">
                <h3 className="sb-card__title">{pkg.title}</h3>
                <p className="sb-card__excerpt">{pkg.description}</p>
                <p className="sb-card__price">
                  {window.sbConfig.symbol}
                  {pkg.price.toFixed(2)} flat
                  {pkg.space_name && ` · ${pkg.space_name}`}
                  {pkg.duration > 0 && ` · ${pkg.duration}h`}
                </p>
              </div>
              {selectedPackage?.id === pkg.id && (
                <span className="sb-card__check" aria-label="Selected">
                  ✓
                </span>
              )}
            </div>
          ))}
          {packages.length === 0 && (
            <p className="sb-empty">No packages available at the moment.</p>
          )}
        </div>
      )}

      <div className="sb-step__actions">
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
