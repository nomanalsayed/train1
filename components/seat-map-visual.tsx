
"use client"

import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ArrowLeft, ArrowRight } from 'lucide-react'
import { useRouter } from 'next/navigation'

interface SeatMapProps {
  coach: {
    coach_id?: number
    coach_code: string
    coach_name?: string
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
  allCoaches?: any[]
  onCoachChange?: (coachCode: string) => void
}

export default function SeatMapVisual({ coach, trainName, route, allCoaches = [], onCoachChange }: SeatMapProps) {
  const router = useRouter()

  const handleBack = () => {
    router.back()
  }

  const handleDone = () => {
    router.push('/')
  }
  
  const renderSeatGrid = (seats: number[], bgColor: string, label: string) => {
    if (!seats || seats.length === 0) return null
    
    const rows = []
    for (let i = 0; i < seats.length; i += 5) { // 5 seats per row (2 left, 3 right)
      const rowSeats = seats.slice(i, i + 5)
      rows.push(
        <div key={`row-${i}`} className="flex justify-center items-center gap-2 mb-2">
          {/* Left side - 2 seats */}
          <div className="flex gap-1">
            {rowSeats.slice(0, 2).map((seatNum, seatIdx) => (
              seatNum ? (
                <div
                  key={`seat-${seatNum}`}
                  className={`w-10 h-10 ${bgColor} rounded-lg text-sm text-white flex items-center justify-center font-semibold shadow-sm border-2 border-white`}
                >
                  {seatNum}
                </div>
              ) : (
                <div key={`empty-left-${i}-${seatIdx}`} className="w-10 h-10"></div>
              )
            ))}
          </div>
          
          {/* Aisle space */}
          <div className="w-8 flex items-center justify-center">
            <div className="w-px h-6 bg-gray-300"></div>
          </div>
          
          {/* Right side - 3 seats */}
          <div className="flex gap-1">
            {rowSeats.slice(2, 5).map((seatNum, seatIdx) => (
              seatNum ? (
                <div
                  key={`seat-${seatNum}`}
                  className={`w-10 h-10 ${bgColor} rounded-lg text-sm text-white flex items-center justify-center font-semibold shadow-sm border-2 border-white`}
                >
                  {seatNum}
                </div>
              ) : (
                <div key={`empty-right-${i}-${seatIdx}`} className="w-10 h-10"></div>
              )
            ))}
          </div>
        </div>
      )
    }

    return (
      <div className={`${bgColor === 'bg-orange-500' ? 'bg-orange-50' : 'bg-emerald-50'} p-6 rounded-xl relative border-2 ${bgColor === 'bg-orange-500' ? 'border-orange-200' : 'border-emerald-200'}`}>
        {/* Direction label */}
        <div className="absolute top-2 right-2">
          <span className={`text-xs font-semibold px-2 py-1 rounded-full ${bgColor === 'bg-orange-500' ? 'bg-orange-200 text-orange-800' : 'bg-emerald-200 text-emerald-800'}`}>
            {label} Facing
          </span>
        </div>
        
        {/* Seats grid */}
        <div className="pt-8">
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
    const allSeats: {number: number, type: string}[] = []

    // Check if seat_layout is an array of seat objects or nested arrays
    if (Array.isArray(coach.seat_layout)) {
      coach.seat_layout.forEach(item => {
        if (Array.isArray(item)) {
          // Nested array structure
          item.forEach(seat => {
            if (typeof seat === 'object' && seat !== null) {
              allSeats.push({
                number: seat.number || seat.seat_number,
                type: seat.color === 'green' || seat.type === 'front_facing' ? 'front_facing' : 'back_facing'
              })
            }
          })
        } else if (typeof item === 'object' && item !== null) {
          // Direct object structure
          allSeats.push({
            number: item.number || item.seat_number,
            type: item.color === 'green' || item.type === 'front_facing' ? 'front_facing' : 'back_facing'
          })
        }
      })
    }

    // Sort all seats by number to maintain sequential order
    allSeats.sort((a, b) => a.number - b.number)

    // Separate into groups while maintaining order
    allSeats.forEach(seat => {
      if (seat.type === 'front_facing') {
        frontSeats.push(seat.number)
      } else {
        backSeats.push(seat.number)
      }
    })

    // If no seats found in layout, generate default
    if (frontSeats.length === 0 && backSeats.length === 0) {
      const totalSeats = coach.total_seats || 40
      const halfSeats = Math.floor(totalSeats / 2)
      const defaultFrontSeats = Array.from({length: halfSeats}, (_, i) => i + 1)
      const defaultBackSeats = Array.from({length: totalSeats - halfSeats}, (_, i) => halfSeats + i + 1)
      
      return renderDefaultLayout(defaultFrontSeats, defaultBackSeats)
    }

    // Render sections sequentially based on seat numbers
    const sections = []
    
    if (backSeats.length > 0) {
      sections.push({
        seats: backSeats,
        color: 'bg-orange-500', // Swapped: back-facing is now orange
        label: 'Backward',
        minSeat: Math.min(...backSeats)
      })
    }
    
    if (frontSeats.length > 0) {
      sections.push({
        seats: frontSeats,
        color: 'bg-emerald-500', // Swapped: front-facing is now emerald
        label: 'Forward',
        minSeat: Math.min(...frontSeats)
      })
    }

    // Sort sections by minimum seat number to maintain sequential order
    sections.sort((a, b) => a.minSeat - b.minSeat)

    return (
      <div className="space-y-6">
        {sections.map((section, index) => (
          <div key={`section-${section.label}-${index}`}>
            {renderSeatGrid(section.seats, section.color, section.label)}
          </div>
        ))}
      </div>
    )
  }

  const renderDefaultLayout = (frontSeats: number[], backSeats: number[]) => {
    return (
      <div className="space-y-6">
        {/* Back-facing seats first (orange) */}
        {backSeats.length > 0 && renderSeatGrid(backSeats, 'bg-orange-500', 'Backward')}
        
        {/* Front-facing seats second (emerald) */}
        {frontSeats.length > 0 && renderSeatGrid(frontSeats, 'bg-emerald-500', 'Forward')}
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50">
      {/* Header */}
      <div className="bg-white shadow-sm border-b p-4 sticky top-0 z-10">
        <div className="flex items-center justify-between max-w-2xl mx-auto">
          <button 
            onClick={handleBack}
            className="text-gray-600 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-colors"
          >
            <ArrowLeft className="w-6 h-6" />
          </button>
          <div className="text-center">
            <h1 className="font-bold text-lg text-gray-800">
              {trainName}
            </h1>
            <p className="text-sm text-gray-500">{coach.coach_name || coach.coach_code}</p>
          </div>
          <button 
            onClick={handleDone}
            className="text-emerald-600 hover:text-emerald-700 text-sm font-medium px-3 py-1 rounded-lg hover:bg-emerald-50 transition-colors"
          >
            Done
          </button>
        </div>
      </div>

      {/* Train info */}
      <div className="max-w-2xl mx-auto p-6">
        <div className="bg-white rounded-2xl p-6 mb-6 shadow-sm border border-gray-100">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h2 className="text-xl font-bold text-gray-800 mb-1">
                {trainName}
              </h2>
              <p className="text-emerald-600 font-medium">{coach.coach_name || `Coach ${coach.coach_code}`}</p>
            </div>
            <div className="text-right">
              <p className="text-sm text-gray-500">Total Seats</p>
              <p className="text-2xl font-bold text-gray-800">{coach.total_seats}</p>
            </div>
          </div>
          
          <div className="flex items-center justify-center space-x-4 bg-gradient-to-r from-emerald-100 to-blue-100 rounded-xl p-4">
            <div className="text-center">
              <p className="font-semibold text-emerald-700">{route.from}</p>
              <p className="text-xs text-gray-500">From</p>
            </div>
            <ArrowRight className="w-6 h-6 text-gray-400" />
            <div className="text-center">
              <p className="font-semibold text-blue-700">{route.to}</p>
              <p className="text-xs text-gray-500">To</p>
            </div>
          </div>
          
          <div className="mt-4 p-3 bg-gray-50 rounded-lg">
            <span className="text-sm text-gray-600">
              <span className="font-medium">Class:</span> {coach.class_name}
            </span>
          </div>
        </div>

        {/* Coach Selection */}
        {allCoaches && allCoaches.length > 1 && (
          <div className="bg-white rounded-2xl p-6 mb-6 shadow-sm border border-gray-100">
            <h3 className="text-lg font-bold text-gray-800 mb-4">Select Coach</h3>
            <div className="grid grid-cols-3 sm:grid-cols-4 gap-3">
              {allCoaches.map((coachOption) => (
                <button
                  key={coachOption.coach_code}
                  onClick={() => onCoachChange && onCoachChange(coachOption.coach_code)}
                  className={`p-3 rounded-lg border-2 transition-colors ${
                    coach.coach_code === coachOption.coach_code
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                      : 'border-gray-200 bg-white text-gray-700 hover:border-emerald-300 hover:bg-emerald-50'
                  }`}
                >
                  <div className="font-semibold">{coachOption.coach_name || coachOption.coach_code}</div>
                  <div className="text-xs text-gray-500 mt-1">{coachOption.total_seats} seats</div>
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Seat map */}
        <div className="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
          <div className="flex items-center justify-between mb-6">
            <h3 className="text-lg font-bold text-gray-800">Seat Layout</h3>
            <div className="flex items-center space-x-4 text-sm">
              <div className="flex items-center space-x-2">
                <div className="w-4 h-4 bg-orange-500 rounded"></div>
                <span className="text-gray-600">Backward</span>
              </div>
              <div className="flex items-center space-x-2">
                <div className="w-4 h-4 bg-emerald-500 rounded"></div>
                <span className="text-gray-600">Forward</span>
              </div>
            </div>
          </div>
          
          {renderSeatLayout()}
        </div>
      </div>
    </div>
  )
}
