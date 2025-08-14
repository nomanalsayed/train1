
"use client"

import { useState, useEffect } from "react"
import { ArrowLeft } from "lucide-react"

interface Props {
  fromStation: string
  toStation: string
  onSelect: (coach: string) => void
  onBack: () => void
}

export function CoachSelection({ fromStation, toStation, onSelect, onBack }: Props) {
  const [coaches, setCoaches] = useState<string[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchCoaches = async () => {
      try {
        console.log("Fetching coaches from API...")
        const response = await fetch("/api/coaches")
        console.log("Coaches API response status:", response.status)

        const data = await response.json()
        console.log("Coaches API data:", data)

        if (response.ok && data.coaches && data.coaches.length > 0) {
          // Extract coach codes/names from the response
          const coachList = data.coaches.map((coach: any) => coach.code || coach.name || "Unknown")
          setCoaches(coachList)
          console.log("Successfully loaded coaches:", coachList)
        } else {
          console.error("Coaches API failed or returned no data:", data)
          // Fallback to default coaches if API fails
          setCoaches([
            "UMA",
            "CHA", 
            "SCHA",
            "JHA",
            "KHA",
            "GHA",
            "TA",
            "THA",
            "DA",
            "DHA",
            "NA",
            "PA",
            "PHA",
            "BA",
            "BHA",
            "MA",
            "YA",
            "RA",
            "LA",
            "SHA",
          ])
        }
      } catch (error) {
        console.error("Failed to fetch coaches:", error)
        // Fallback to default coaches if API fails
        setCoaches([
          "UMA",
          "CHA",
          "SCHA", 
          "JHA",
          "KHA",
          "GHA",
          "TA",
          "THA",
          "DA",
          "DHA",
          "NA",
          "PA",
          "PHA",
          "BA",
          "BHA",
          "MA",
          "YA",
          "RA",
          "LA", 
          "SHA",
        ])
      } finally {
        setLoading(false)
      }
    }

    fetchCoaches()
  }, [])

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
                key={coach}
                onClick={() => onSelect(coach)}
                className="bg-white border-2 border-gray-200 rounded-lg p-4 text-center hover:border-emerald-500 hover:bg-emerald-50 transition-colors"
              >
                <div className="text-lg font-bold text-gray-900">{coach}</div>
                <div className="text-xs text-gray-500 mt-1">Coach</div>
              </button>
            ))}
          </div>
          
          {coaches.length === 0 && (
            <div className="text-center py-12">
              <p className="text-gray-500">No coaches available</p>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
