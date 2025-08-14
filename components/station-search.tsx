"use client"

import { useState, useEffect } from "react"
import { ArrowLeft, X, MapPin } from "lucide-react"
import { Button } from "@/components/ui/button"

interface StationSearchProps {
  onSelect: (station: string) => void
  onBack: () => void
}

export function StationSearch({ onSelect, onBack }: StationSearchProps) {
  const [query, setQuery] = useState("")
  const [stations, setStations] = useState<string[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let isMounted = true
    const fetchStations = async () => {
      try {
        const response = await fetch("/api/stations")
        if (response.ok && isMounted) {
          const data = await response.json()
          setStations(
            data.stations
              ?.map((station: any) => station?.title)
              .filter((title: any) => title && typeof title === "string") || [],
          )
        }
      } catch (error) {
        console.error("Failed to fetch stations:", error)
        if (isMounted) {
          // Fallback to default stations if API fails
          setStations([
            "Dhaka",
            "Dhaka Cantonment",
            "Panchagarh",
            "Chittagong",
            "Sylhet",
            "Rangpur",
            "Rajshahi",
            "Khulna",
            "Barisal",
            "Mymensingh",
          ])
        }
      } finally {
        if (isMounted) {
          setLoading(false)
        }
      }
    }

    fetchStations()
    return () => { isMounted = false }
  }, [])

  const filteredStations = stations.filter(
    (station) => station && typeof station === "string" && station.toLowerCase().includes(query.toLowerCase()),
  )

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="px-4 py-4 bg-white border-b border-gray-200">
        <div className="flex items-center space-x-4">
          <Button variant="ghost" size="sm" onClick={onBack} className="p-2">
            <ArrowLeft className="w-5 h-5" />
          </Button>
          <h2 className="text-lg font-semibold text-gray-900">Select Station</h2>
        </div>
      </div>

      <div className="p-4 space-y-4">
        {/* Search Input */}
        <div className="relative">
          <input
            type="text"
            placeholder="Search station..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            className="w-full p-4 pr-12 rounded-md bg-white border border-gray-200 focus:border-emerald-500 focus:outline-none transition-colors"
          />
          {query && (
            <button
              onClick={() => setQuery("")}
              className="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
            >
              <X className="w-5 h-5" />
            </button>
          )}
        </div>

        {/* Station List */}
        <div className="bg-white rounded-md border border-gray-200 overflow-hidden">
          {loading ? (
            <div className="p-4 text-center text-gray-500">Loading stations...</div>
          ) : filteredStations.length > 0 ? (
            filteredStations.map((station, index) => (
              <button
                key={station}
                onClick={() => onSelect(station)}
                className={`w-full p-4 text-left hover:bg-emerald-50 transition-colors flex items-center space-x-3 ${
                  index !== filteredStations.length - 1 ? "border-b border-gray-100" : ""
                }`}
              >
                <MapPin className="w-4 h-4 text-emerald-600" />
                <span className="font-medium text-gray-900">{station}</span>
              </button>
            ))
          ) : (
            <div className="p-4 text-center text-gray-500">No stations found</div>
          )}
        </div>
      </div>
    </div>
  )
}