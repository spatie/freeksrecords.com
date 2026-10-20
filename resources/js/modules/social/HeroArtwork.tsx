import { useState } from 'react';

export function HeroArtwork({ covers }: { covers: string[] }) {
    const [paused, setPaused] = useState(false);
    return (
        <div
            className="hero-artwork"
            data-artwork-state={paused ? 'paused' : 'running'}
        >
            <div className="record-wall" aria-hidden="true">
                <div className="record-wall-columns">
                    {Array.from(
                        { length: Math.ceil(covers.length / 8) },
                        (_, column) => (
                            <div className="record-wall-column" key={column}>
                                <div className="record-wall-track">
                                    {[0, 1].map((copy) => (
                                        <div
                                            className="record-wall-group"
                                            key={copy}
                                        >
                                            {covers
                                                .slice(
                                                    column * 8,
                                                    column * 8 + 8,
                                                )
                                                .map((cover, index) => (
                                                    <img
                                                        key={index}
                                                        src={cover}
                                                        alt=""
                                                        width="180"
                                                        height="180"
                                                    />
                                                ))}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ),
                    )}
                </div>
            </div>
            <button
                className="artwork-toggle"
                type="button"
                onClick={() => setPaused(!paused)}
            >
                {paused ? 'Resume artwork' : 'Pause artwork'}
            </button>
        </div>
    );
}
