import { SeatDirectionViewer } from "@/components/seat-direction-viewer"
import { notFound } from "next/navigation"

interface Props {
  params: { trainId: string }
  searchParams: {
    from?: string
    to?: string
    direction?: string
    trainName?: string
    trainNumber?: string
    coach?: string
  }
}

async function getTrainDetail(trainId: string) {
  try {
    const response = await fetch(`/api/trains/${trainId}/detail`, {
      cache: "no-store",
    })

    if (!response.ok) {
      console.error(`API responded with status: ${response.status}`)
      return null
    }

    return await response.json()
  } catch (error) {
    console.error("Error fetching train detail:", error)
    return null
  }
}

export default async function TrainDirectionPage({ params, searchParams }: Props) {
  const train = await getTrainDetail(params.trainId)

  if (!train) {
    notFound()
  }

  const trainName = searchParams.trainName || train.train_name || "TRAIN"
  const trainNumber = searchParams.trainNumber || train.train_number || "000"
  const fromStation = searchParams.from || "DHAKA"
  const toStation = searchParams.to || "PANCHAGARH"
  const selectedCoach = searchParams.coach || "UMA"

  const direction = Number.parseInt(train.train_number) % 2 === 1 ? "dhaka-panchagarh" : "panchagarh-dhaka"

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="container mx-auto px-4 py-8">
        <SeatDirectionViewer
          trainId={params.trainId}
          trainName={trainName}
          trainNumber={trainNumber}
          from={fromStation}
          to={toStation}
          filterCoach={selectedCoach}
          initialDirection={direction as "dhaka-panchagarh" | "panchagarh-dhaka"}
        />
      </div>
    </div>
  )
}
