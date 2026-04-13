import React, { useEffect, useState } from 'react';
import { useBookingStore } from '@/store/bookingStore';
import { fetchSpaces, fetchPackages } from '@/utils/api';
import type { Space, Package } from '@/types';

export function Step1Selection() {
  const { selectedSpace, selectedPackage, setSpace, setPackage, nextStep } = useBookingStore();
  const [spaces,   setSpaces]   = useState<Space[]>([]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [loading,  setLoading]  = useState(true);
  const [tab,      setTab]      = useState<'space' | 'package'>('space');

  useEffect(() => {
    setLoading(true);
    Promise.all([fetchSpaces(), fetchPackages()])
      .then(([s, p]) => { setSpaces(s); setPackages(p); })
      .finally(() => setLoading(false));
  }, []);

  const canProceed = selectedSpace !== null || selectedPackage !== null;

  return (
    <div className="sb-step sb-step-1">
      <h2 className="sb-step__title">Choose a Space or Package</h2>

      {/* Tabs */}
      <div className="sb-tabs">
        <button
          className={`sb-tab ${tab === 'space' ? 'sb-tab--active' : ''}`}
          onClick={() => setTab('space')}
        >
          Spaces
        </button>
        <button
          className={`sb-tab ${tab === 'package' ? 'sb-tab--active' : ''}`}
          onClick={() => setTab('package')}
        >
          Packages
        </button>
      </div>

      {loading && <div className="sb-loading">Loading…</div>}

      {/* Spaces */}
      {!loading && tab === 'space' && (
        <div className="sb-cards">
          {spaces.map((space) => (
            <div
              key={space.id}
              className={`sb-card ${selectedSpace?.id === space.id ? 'sb-card--selected' : ''}`}
              onClick={() => setSpace(space)}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => e.key === 'Enter' && setSpace(space)}
            >
              {space.thumbnail && (
                <img src={space.thumbnail} alt={space.title} className="sb-card__img" />
              )}
              <div className="sb-card__body">
                <h3 className="sb-card__title">{space.title}</h3>
                <p className="sb-card__excerpt">{space.excerpt}</p>
                <p className="sb-card__price">
                  {window.sbConfig.symbol}{space.hourly_rate.toFixed(2)} / hr
                  {space.capacity > 0 && ` · Up to ${space.capacity} guests`}
                </p>

              </div>
              {selectedSpace?.id === space.id && (
                <span className="sb-card__check" aria-label="Selected">✓</span>
              )}
            </div>
          ))}
          {spaces.length === 0 && (
            <p className="sb-empty">No spaces available at the moment.</p>
          )}
        </div>
      )}

      {/* Packages */}
      {!loading && tab === 'package' && (
        <div className="sb-cards">
          {packages.map((pkg) => (
            <div
              key={pkg.id}
              className={`sb-card ${selectedPackage?.id === pkg.id ? 'sb-card--selected' : ''}`}
              onClick={() => setPackage(pkg)}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => e.key === 'Enter' && setPackage(pkg)}
            >
              {pkg.thumbnail && (
                <img src={pkg.thumbnail} alt={pkg.title} className="sb-card__img" />
              )}
              <div className="sb-card__body">
                <h3 className="sb-card__title">{pkg.title}</h3>
                <p className="sb-card__excerpt">{pkg.description}</p>
                <p className="sb-card__price">
                  {window.sbConfig.symbol}{pkg.price.toFixed(2)} flat
                  {pkg.space_name && ` · ${pkg.space_name}`}
                  {pkg.duration > 0 && ` · ${pkg.duration}h`}
                </p>

              </div>
              {selectedPackage?.id === pkg.id && (
                <span className="sb-card__check" aria-label="Selected">✓</span>
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
