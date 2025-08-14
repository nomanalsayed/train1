
"use client"

import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ArrowLeft, ArrowRight } from 'lucide-react'

interface SeatMapProps {
  coach: {
    coach_code: string
    type: string
    class_name: string
    total_seats: number
    seat_layout?: any[]
    direction?: string
    route_code?: string
  }
  trainName: string
  route: {
    from: string
    to: string
  }
}

export default function SeatMapVisual({ coach, trainName, route }: SeatMapProps) {
  
  const renderSeatGrid = (seats: number[], bgColor: string, label: string) => {
    const rows = []
    for (let i = 0; i < seats.length; i += 10) { // 10 seats per row (5 left, 5 right)
      const rowSeats = seats.slice(i, i + 10)
      rows.push(
        <div key={i} className="flex justify-center gap-1 mb-1">
          {/* Left side - 5 seats */}
          <div className="flex gap-1">
            {rowSeats.slice(0, 5).map(seatNum => (
              <div
                key={seatNum}
                className={`w-8 h-8 ${bgColor} rounded text-xs text-white flex items-center justify-center font-medium`}
              >
                {seatNum}
              </div>
            ))}
          </div>
          
          {/* Aisle space */}
          <div className="w-4"></div>
          
          {/* Right side - 5 seats */}
          <div className="flex gap-1">
            {rowSeats.slice(5, 10).map(seatNum => (
              <div
                key={seatNum}
                className={`w-8 h-8 ${bgColor} rounded text-xs text-white flex items-center justify-center font-medium`}
              >
                {seatNum}
              </div>
            ))}
          </div>
        </div>
      )
    }

    return (
      <div className={`${bgColor === 'bg-orange-500' ? 'bg-orange-100' : 'bg-green-100'} p-4 rounded-lg relative`}>
        {/* Direction label */}
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
          <div 
            className="text-4xl font-bold text-black opacity-20 transform -rotate-90"
            style={{ writingMode: 'vertical-lr' }}
          >
            {label}
          </div>
        </div>
        
        {/* Seats grid */}
        <div className="relative z-10">
          {rows}
        </div>
      </div>
    )
  }

  const renderSeatLayout = () => {
    if (!coach.seat_layout || coach.seat_layout.length === 0) {
      // Generate default layout based on total seats
      const totalSeats = coach.total_seats || 40
      const halfSeats = Math.floor(totalSeats / 2)
      const frontSeats = Array.from({length: halfSeats}, (_, i) => i + 1)
      const backSeats = Array.from({length: totalSeats - halfSeats}, (_, i) => halfSeats + i + 1)
      
      return renderDefaultLayout(frontSeats, backSeats)
    }

    // Handle seat layout data structure
    const frontSeats: number[] = []
    const backSeats: number[] = []

    // Check if seat_layout is an array of seat objects or nested arrays
    if (Array.isArray(coach.seat_layout)) {
      coach.seat_layout.forEach(item => {
        if (Array.isArray(item)) {
          // Nested array structure
          item.forEach(seat => {
            if (typeof seat === 'object' && seat !== null) {
              if (seat.color === 'green' || seat.type === 'front_facing') {
                frontSeats.push(seat.number || seat.seat_number)
              } else {
                backSeats.push(seat.number || seat.seat_number)
              }
            }
          })
        } else if (typeof item === 'object' && item !== null) {
          // Direct object structure
          if (item.color === 'green' || item.type === 'front_facing') {
            frontSeats.push(item.number || item.seat_number)
          } else {
            backSeats.push(item.number || item.seat_number)
          }
        }
      })
    }

    // If no seats found in layout, generate default
    if (frontSeats.length === 0 && backSeats.length === 0) {
      const totalSeats = coach.total_seats || 40
      const halfSeats = Math.floor(totalSeats / 2)
      const defaultFrontSeats = Array.from({length: halfSeats}, (_, i) => i + 1)
      const defaultBackSeats = Array.from({length: totalSeats - halfSeats}, (_, i) => halfSeats + i + 1)
      
      return renderDefaultLayout(defaultFrontSeats, defaultBackSeats)
    }

    // Sort seats
    frontSeats.sort((a, b) => a - b)
    backSeats.sort((a, b) => a - b)

    return (
      <div className="space-y-4">
        {/* Back-facing seats (orange/gray in original) */}
        {backSeats.length > 0 && renderSeatGrid(backSeats, 'bg-orange-500', 'Back')}
        
        {/* Front-facing seats (green) */}
        {frontSeats.length > 0 && renderSeatGrid(frontSeats, 'bg-green-500', 'Front')}
      </div>
    )
  }

  const renderDefaultLayout = (frontSeats: number[], backSeats: number[]) => {
    return (
      <div className="space-y-4">
        {/* Back-facing seats */}
        {backSeats.length > 0 && renderSeatGrid(backSeats, 'bg-orange-500', 'Back')}
        
        {/* Front-facing seats */}
        {frontSeats.length > 0 && renderSeatGrid(frontSeats, 'bg-green-500', 'Front')}
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b p-4">
        <div className="flex items-center justify-between max-w-md mx-auto">
          <button className="text-gray-600 hover:text-gray-800">
            <ArrowLeft className="w-6 h-6" />
          </button>
          <div className="text-center">
            <h1 className="font-semibold text-gray-800">
              {trainName} ({coach.coach_code})
            </h1>
          </div>
          <button className="text-blue-600 hover:text-blue-800 text-sm">
            Cancel
          </button>
        </div>
      </div>

      {/* Train info */}
      <div className="max-w-md mx-auto p-4">
        <div className="bg-white rounded-lg p-4 mb-4">
          <h2 className="text-orange-600 font-semibold mb-2">
            {trainName} ({coach.coach_code})
          </h2>
          
          <div className="flex items-center justify-between mb-2">
            <span className="text-green-600 font-medium">{route.from}</span>
            <ArrowRight className="w-4 h-4 text-gray-400" />
            <span className="text-green-600 font-medium">{route.to}</span>
          </div>
          
          <div className="text-sm text-gray-600">
            <span className="font-medium">Class:</span> {coach.class_name}
          </div>
        </div>

        {/* Seat map */}
        <div className="bg-white rounded-lg p-4">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-800">Seats</h3>
            <span className="text-sm text-gray-500">
              {coach.total_seats} seats total
            </span>
          </div>
          
          {renderSeatLayout()}
        </div>
      </div>
    </div>
  )
}
