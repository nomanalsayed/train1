
"use client"

import { useState, useEffect } from "react"
import { ArrowLeft } from "lucide-react"

interface Props {
  fromStation: string
  toStation: string
  trainId?: string
  onSelect: (coach: string) => void
  onBack: () => void
}

interface CoachData {
  code: string
  name: string
  totalSeats: number
  type: string
}

export function CoachSelection({ fromStation, toStation, trainId, onSelect, onBack }: Props) {
  const [coaches, setCoaches] = useState<CoachData[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchCoaches = async () => {
      try {
        console.log("Fetching coaches from API...")
        
        // If we have a trainId, get train-specific coaches
        let response
        if (trainId) {
          response = await fetch(`/api/trains/${trainId}/detail?from=${encodeURIComponent(fromStation)}&to=${encodeURIComponent(toStation)}`)
          console.log("Train detail API response status:", response.status)

          const data = await response.json()
          console.log("Train detail API data:", data)

          if (response.ok && data.classes && data.classes.length > 0) {
            const allCoaches: CoachData[] = []
            
            data.classes.forEach((trainClass: any) => {
              if (trainClass.coaches && Array.isArray(trainClass.coaches)) {
                trainClass.coaches.forEach((coach: any) => {
                  allCoaches.push({
                    code: coach.code,
                    name: coach.code,
                    totalSeats: coach.totalSeats || 0,
                    type: trainClass.shortCode || 'Unknown'
                  })
                })
              }
            })

            if (allCoaches.length > 0) {
              setCoaches(allCoaches)
              console.log("Successfully loaded train-specific coaches:", allCoaches.map(c => c.code))
              setLoading(false)
              return
            }
          }
        }

        // Fallback to generic coaches
        response = await fetch("/api/coaches")
        console.log("Generic coaches API response status:", response.status)

        const data = await response.json()
        console.log("Generic coaches API data:", data)

        if (response.ok && data.coaches && data.coaches.length > 0) {
          const coachList = data.coaches.map((coach: any) => ({
            code: coach.code || coach.name || "Unknown",
            name: coach.name || coach.code || "Unknown Coach",
            totalSeats: coach.total_seats || 0,
            type: coach.code || 'Unknown'
          }))
          setCoaches(coachList)
          console.log("Successfully loaded generic coaches:", coachList.map(c => c.code))
        } else {
          console.error("Coaches API failed or returned no data:", data)
          // Fallback to default coaches if API fails
          const fallbackCoaches = [
            { code: "UMA", name: "UMA Coach", totalSeats: 60, type: "UMA" },
            { code: "CHA", name: "CHA Coach", totalSeats: 80, type: "CHA" },
            { code: "SCHA", name: "SCHA Coach", totalSeats: 75, type: "SCHA" },
            { code: "JHA", name: "JHA Coach", totalSeats: 70, type: "JHA" },
            { code: "KHA", name: "KHA Coach", totalSeats: 65, type: "KHA" },
          ]
          setCoaches(fallbackCoaches)
        }
      } catch (error) {
        console.error("Failed to fetch coaches:", error)
        // Fallback to default coaches if API fails
        const fallbackCoaches = [
          { code: "UMA", name: "UMA Coach", totalSeats: 60, type: "UMA" },
          { code: "CHA", name: "CHA Coach", totalSeats: 80, type: "CHA" },
          { code: "SCHA", name: "SCHA Coach", totalSeats: 75, type: "SCHA" },
          { code: "JHA", name: "JHA Coach", totalSeats: 70, type: "JHA" },
          { code: "KHA", name: "KHA Coach", totalSeats: 65, type: "KHA" },
        ]
        setCoaches(fallbackCoaches)
      } finally {
        setLoading(false)
      }
    }

    fetchCoaches()
  }, [trainId, fromStation, toStation])

  return (
    <div className="min-h-screen bg-gradient-to-br from-green-50 to-emerald-50">
      {/* Header */}
      <div className="bg-white shadow-sm">
        <div className="px-4 py-4 flex items-center space-x-3">
          <button onClick={onBack} className="p-1">
            <ArrowLeft className="w-6 h-6 text-gray-700" />
          </button>
          <h1 className="text-lg font-bold text-gray-900">Select Coach</h1>
        </div>
      </div>

      {/* Route info */}
      <div className="px-6 py-4 bg-white">
        <div className="text-center">
          <p className="text-sm text-gray-500 mb-1">FROM</p>
          <p className="font-bold text-gray-900">{fromStation}</p>
          <div className="flex justify-center my-2">
            <div className="w-8 h-0.5 bg-emerald-500"></div>
          </div>
          <p className="text-sm text-gray-500 mb-1">TO</p>
          <p className="font-bold text-gray-900">{toStation}</p>
        </div>
      </div>

      {/* Loading state */}
      {loading && (
        <div className="flex justify-center items-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
        </div>
      )}

      {/* Coach Grid */}
      {!loading && (
        <div className="px-6 py-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Available Coaches</h2>
          <div className="grid grid-cols-3 sm:grid-cols-4 gap-3">
            {coaches.map((coach) => (
              <button
                key={coach.code}
                onClick={() => onSelect(coach.code)}
                className="bg-white border-2 border-gray-200 rounded-lg p-4 text-center hover:border-emerald-500 hover:bg-emerald-50 transition-colors"
              >
                <div className="text-lg font-bold text-gray-900">{coach.code}</div>
                <div className="text-xs text-gray-500 mt-1">{coach.name}</div>
                {coach.totalSeats > 0 && (
                  <div className="text-xs text-gray-400 mt-0.5">{coach.totalSeats} seats</div>
                )}
              </button>
            ))}
          </div>
          
          {coaches.length === 0 && (
            <div className="text-center py-12">
              <p className="text-gray-500">No coaches available</p>
            </div>
          )}
        </div>
      )}</div>
    )
  }
}
    </div>
  )
}
