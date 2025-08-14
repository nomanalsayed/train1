
import React from 'react'

interface SeatInfo {
  number: number
  type: 'front_facing' | 'back_facing'
  color: 'green' | 'gray'
  isAvailable?: boolean
}

interface SeatMapVisualProps {
  seatLayout: SeatInfo[][]
  coachCode: string
  direction: 'forward' | 'reverse'
  routeCode?: string
}

export function SeatMapVisual({ seatLayout, coachCode, direction, routeCode }: SeatMapVisualProps) {
  if (!seatLayout || seatLayout.length === 0) {
    return (
      <div className="text-center py-8 text-gray-500">
        No seat layout available for coach {coachCode}
      </div>
    )
  }

  return (
    <div className="bg-white rounded-lg border shadow-sm p-6">
      <div className="mb-4 text-center">
        <h3 className="text-lg font-semibold">Coach {coachCode}</h3>
        <p className="text-sm text-gray-600">
          Direction: {direction === 'forward' ? 'A to B' : 'B to A'}
          {routeCode && ` (${routeCode})`}
        </p>
      </div>

      <div className="space-y-2">
        {seatLayout.map((row, rowIndex) => (
          <div key={rowIndex} className="flex justify-center gap-2">
            {/* Left section - 5 seats */}
            <div className="flex gap-1">
              {row.slice(0, 5).map((seat) => (
                <div
                  key={seat.number}
                  className={`
                    w-10 h-10 rounded border-2 flex items-center justify-center text-xs font-medium
                    ${seat.color === 'green' 
                      ? 'bg-green-200 border-green-300 text-green-800' 
                      : 'bg-gray-200 border-gray-300 text-gray-800'
                    }
                    ${!seat.isAvailable ? 'opacity-50' : ''}
                  `}
                >
                  {seat.number}
                </div>
              ))}
            </div>

            {/* Aisle gap */}
            <div className="w-4"></div>

            {/* Right section - 5 seats */}
            <div className="flex gap-1">
              {row.slice(5).map((seat) => (
                <div
                  key={seat.number}
                  className={`
                    w-10 h-10 rounded border-2 flex items-center justify-center text-xs font-medium
                    ${seat.color === 'green' 
                      ? 'bg-green-200 border-green-300 text-green-800' 
                      : 'bg-gray-200 border-gray-300 text-gray-800'
                    }
                    ${!seat.isAvailable ? 'opacity-50' : ''}
                  `}
                >
                  {seat.number}
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>

      <div className="mt-4 flex justify-center gap-6 text-sm">
        <div className="flex items-center gap-2">
          <div className="w-4 h-4 bg-green-200 border border-green-300 rounded"></div>
          <span>Forward Facing</span>
        </div>
        <div className="flex items-center gap-2">
          <div className="w-4 h-4 bg-gray-200 border border-gray-300 rounded"></div>
          <span>Backward Facing</span>
        </div>
      </div>
    </div>
  )
}
